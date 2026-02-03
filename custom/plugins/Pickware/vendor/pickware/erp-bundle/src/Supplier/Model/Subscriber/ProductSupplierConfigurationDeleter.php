<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Model\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\Supplier\MultipleSuppliersPerProductProductionFeatureFlag;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSupplierConfigurationDeleter implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
        private readonly Connection $connection,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteValidationEventType::Pre->getEventName(ProductSupplierConfigurationDefinition::ENTITY_NAME) => (
                'onPreWriteValidationEvent'
            ),
        ];
    }

    public function onPreWriteValidationEvent(EntityPreWriteValidationEvent $event): void
    {
        if ($this->featureFlagService->isActive(MultipleSuppliersPerProductProductionFeatureFlag::NAME)) {
            return;
        }

        $deleteCommands = (new ImmutableCollection($event->getCommands()))->filter(fn($command) => $command instanceof DeleteCommand);
        $this->deleteAllProductSupplierConfigurationsIfMultipleSuppliersPerProductIsNotActive($deleteCommands, $event->getContext());

        $updateCommands = (new ImmutableCollection($event->getCommands()))->filter(fn($command) => $command instanceof UpdateCommand);
        $this->deleteUniqueConstraintViolationCausingProductSupplierConfigurations($updateCommands, $event->getContext());
    }

    /**
     * If the customer deletes a product supplier configuration for a product while the multiple suppliers per product
     * feature flag is _not_ active, we want to delete any other product supplier configuration they might have had for
     * that product while the feature flag was active on at some point.
     * Reason being: the customer sees only one product supplier configuration per product in the administration and
     * deletes it. The result must be: no supplier configurations exist anymore.
     *
     * @param ImmutableCollection<DeleteCommand> $deleteCommands
     */
    private function deleteAllProductSupplierConfigurationsIfMultipleSuppliersPerProductIsNotActive(
        ImmutableCollection $deleteCommands,
        Context $context,
    ): void {
        /** @var string[] $idsOfProductSupplierConfigurationsToBeDeleted */
        $idsOfProductSupplierConfigurationsToBeDeleted = $deleteCommands
            ->map(fn(DeleteCommand $deleteCommand) => bin2hex($deleteCommand->getPrimaryKey()['id']))
            ->asArray();
        if (count($idsOfProductSupplierConfigurationsToBeDeleted) === 0) {
            return;
        }

        /** @var string[] $productIdsOfProductSupplierConfigurationsToBeDeleted */
        $productIdsOfProductSupplierConfigurationsToBeDeleted = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            ['pickwareErpProductSupplierConfigurations.id' => $idsOfProductSupplierConfigurationsToBeDeleted],
            $context,
        );
        if (count($productIdsOfProductSupplierConfigurationsToBeDeleted) === 0) {
            return;
        }

        $this->connection->executeStatement(
            <<<SQL
                DELETE FROM `pickware_erp_product_supplier_configuration`
                WHERE `product_id` IN (:productIds)
                AND `id` NOT IN (:productSupplierConfigurationIds)
                SQL,
            [
                'productIds' => array_map('hex2bin', $productIdsOfProductSupplierConfigurationsToBeDeleted),
                'productSupplierConfigurationIds' => array_map('hex2bin', $idsOfProductSupplierConfigurationsToBeDeleted),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
                'productSupplierConfigurationIds' => ArrayParameterType::STRING,
            ],
        );
    }

    /**
     * If the customer changes the supplier of a product supplier configuration for a product while the multiple
     * suppliers per product feature flag is _not_ active, they might change it to a supplier that is already
     * configured in a non-default supplier configuration for that product. If the feature flag is not active, the
     * customer cannot see or modify non-default supplier configurations. To prevent unexpected unique constraint
     * violations, we delete any product supplier configurations with the same supplier.
     *
     * @param ImmutableCollection<UpdateCommand> $updateCommands
     */
    public function deleteUniqueConstraintViolationCausingProductSupplierConfigurations(
        ImmutableCollection $updateCommands,
        Context $context,
    ): void {
        $newSupplierIdByProductSupplierConfigurationId = $updateCommands
            ->filter(fn(UpdateCommand $updateCommand) => isset($updateCommand->getPayload()['supplier_id']))
            ->reduce([], function(array $changeSets, UpdateCommand $updateCommand) {
                $changeSets[bin2hex($updateCommand->getPrimaryKey()['id'])] = bin2hex($updateCommand->getPayload()['supplier_id']);

                return $changeSets;
            });
        if (count($newSupplierIdByProductSupplierConfigurationId) === 0) {
            return;
        }

        /** @var ProductSupplierConfigurationCollection $productSupplierConfigurations */
        $productSupplierConfigurations = $this->entityManager->findBy(
            ProductSupplierConfigurationDefinition::class,
            ['id' => array_keys($newSupplierIdByProductSupplierConfigurationId)],
            $context,
            ['product.pickwareErpProductSupplierConfigurations'],
        );

        $idsOfProductSupplierConfigurationsToCauseUniqueConstraintViolation = [];
        foreach ($newSupplierIdByProductSupplierConfigurationId as $productSupplierConfigurationId => $newSupplierId) {
            $conflictingProductSupplierConfiguration = $productSupplierConfigurations
                ->get($productSupplierConfigurationId)
                ->getProduct()
                ->getExtension('pickwareErpProductSupplierConfigurations')
                ->filter(
                    fn(ProductSupplierConfigurationEntity $productSupplierConfiguration) => (
                        $productSupplierConfiguration->getSupplierId() === $newSupplierId
                        && $productSupplierConfiguration->getId() !== $productSupplierConfigurationId
                    ),
                )
                ->first();
            if ($conflictingProductSupplierConfiguration !== null) {
                $idsOfProductSupplierConfigurationsToCauseUniqueConstraintViolation[] = $conflictingProductSupplierConfiguration->getId();
            }
        }

        $this->connection->executeStatement(
            <<<SQL
                DELETE FROM `pickware_erp_product_supplier_configuration`
                WHERE `id` IN (:productSupplierConfigurationIds)
                SQL,
            [
                'productSupplierConfigurationIds' => array_map('hex2bin', $idsOfProductSupplierConfigurationsToCauseUniqueConstraintViolation),
            ],
            [
                'productSupplierConfigurationIds' => ArrayParameterType::STRING,
            ],
        );
    }
}
