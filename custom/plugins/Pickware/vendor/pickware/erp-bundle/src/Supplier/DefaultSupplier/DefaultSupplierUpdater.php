<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\DefaultSupplier;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DefaultSupplierUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PickwareProductDefinition::ENTITY_WRITTEN_EVENT => (
                'updateProductDefaultSupplierConfigurationOnPickwareProductWrite'
            ),
            ProductSupplierConfigurationDefinition::ENTITY_WRITTEN_EVENT => (
                'onProductSupplierConfigurationWrite'
            ),
            ProductSupplierConfigurationDefinition::ENTITY_DELETED_EVENT => (
                'setNewDefaultSupplierOnProductSupplierConfigurationDeletion'
            ),
            EntityWriteValidationEventType::Pre->getEventName(ProductSupplierConfigurationDefinition::ENTITY_NAME) => (
                'requestChangeSet'
            ),
        ];
    }

    /**
     * After the default supplier of a PickwareProduct changes, we need to update the `supplierIsDefault` flag of the
     * product supplier configurations of that product (i.e. move it from supplier A to supplier B).
     */
    public function updateProductDefaultSupplierConfigurationOnPickwareProductWrite(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /** @var string[] $idsOfPickwareProductsWithNewDefaultSupplier */
        $idsOfPickwareProductsWithNewDefaultSupplier = (new ImmutableCollection($event->getWriteResults()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getOperation() !== EntityWriteResult::OPERATION_DELETE)
            ->filter(fn(EntityWriteResult $writeResult) => array_key_exists('defaultSupplierId', $writeResult->getPayload()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getPayload()['defaultSupplierId'] !== null)
            ->map(fn(EntityWriteResult $writeResult) => $writeResult->getPrimaryKey())
            ->asArray();
        if (count($idsOfPickwareProductsWithNewDefaultSupplier) === 0) {
            return;
        }

        /** @var string[] $idsOfProductsWithNewDefaultSupplier */
        $idsOfProductsWithNewDefaultSupplier = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            ['pickwareErpPickwareProduct.id' => $idsOfPickwareProductsWithNewDefaultSupplier],
            $event->getContext(),
        );
        if (count($idsOfProductsWithNewDefaultSupplier) === 0) {
            return;
        }

        $this->updateDefaultSupplierFlagOfProductSupplierConfigurations($idsOfProductsWithNewDefaultSupplier);
        $this->eventDispatcher->dispatch(new DefaultSupplierUpdatedEvent($idsOfProductsWithNewDefaultSupplier));
    }

    public function onProductSupplierConfigurationWrite(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $this->setDefaultSupplierIfNoDefaultExistsOnProductSupplierConfigurationInsert($event);
        $this->updatePickwareProductDefaultSupplierOnSupplierConfigurationSupplierUpdate($event);
    }

    /**
     * After inserting a new product supplier configuration for a pickware product which does not yet have a default
     * supplier, we set the default supplier of the product for it.
     */
    private function setDefaultSupplierIfNoDefaultExistsOnProductSupplierConfigurationInsert(
        EntityWrittenEvent $event,
    ): void {
        /** @var string[] $productIdsOfInsertedProductSupplierConfigurations */
        $productIdsOfInsertedProductSupplierConfigurations = (new ImmutableCollection($event->getWriteResults()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT)
            ->filter(fn(EntityWriteResult $writeResult) => isset($writeResult->getPayload()['productId']))
            ->map(fn(EntityWriteResult $writeResult) => $writeResult->getPayload()['productId'])
            ->asArray();
        if (count($productIdsOfInsertedProductSupplierConfigurations) === 0) {
            return;
        }

        /** @var string[] $productIdsOfInsertedProductSupplierConfigurationsWithoutDefaultSupplier */
        $productIdsOfInsertedProductSupplierConfigurationsWithoutDefaultSupplier = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            [
                'id' => $productIdsOfInsertedProductSupplierConfigurations,
                'pickwareErpPickwareProduct.defaultSupplierId' => null,
            ],
            $event->getContext(),
        );
        if (count($productIdsOfInsertedProductSupplierConfigurationsWithoutDefaultSupplier) === 0) {
            return;
        }

        $this->setAlphanumericallyFirstSupplierAsDefaultSupplier($productIdsOfInsertedProductSupplierConfigurationsWithoutDefaultSupplier);
        $this->eventDispatcher->dispatch(new DefaultSupplierInitialySetEvent(
            $productIdsOfInsertedProductSupplierConfigurationsWithoutDefaultSupplier,
            $event->getContext(),
        ));
    }

    /**
     * When the supplier of the default supplier configuration of a product changes, we need to update the default
     * supplier of the respective Pickware product.
     */
    private function updatePickwareProductDefaultSupplierOnSupplierConfigurationSupplierUpdate(EntityWrittenEvent $event): void
    {
        /** @var string[] $idsOfProductSupplierConfigurationsWithNewSupplier */
        $idsOfProductSupplierConfigurationsWithNewSupplier = (new ImmutableCollection($event->getWriteResults()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE)
            ->filter(fn(EntityWriteResult $writeResult) => isset($writeResult->getPayload()['supplierId']))
            ->map(fn(EntityWriteResult $writeResult) => $writeResult->getPrimaryKey())
            ->asArray();
        if (count($idsOfProductSupplierConfigurationsWithNewSupplier) === 0) {
            return;
        }

        $this->connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_pickware_product` AS `pickwareProduct`
                INNER JOIN `pickware_erp_product_supplier_configuration` AS `productSupplierConfiguration`
                    ON `pickwareProduct`.`product_id` = `productSupplierConfiguration`.`product_id`
                    AND `pickwareProduct`.`product_version_id` = `productSupplierConfiguration`.`product_version_id`
                    AND `productSupplierConfiguration`.`supplier_is_default` = 1

                SET `pickwareProduct`.`default_supplier_id` = `productSupplierConfiguration`.`supplier_id`

                WHERE `productSupplierConfiguration`.`id` IN (:idsOfProductSupplierConfigurationsWithNewSupplier)
                    AND `pickwareProduct`.`product_version_id` = :liveVersionId
                SQL,
            [
                'idsOfProductSupplierConfigurationsWithNewSupplier' => array_map('hex2bin', $idsOfProductSupplierConfigurationsWithNewSupplier),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            ['idsOfProductSupplierConfigurationsWithNewSupplier' => ArrayParameterType::STRING],
        );
    }

    /**
     * If the supplier configuration for a default supplier was deleted, we select a new supplier to be the default if
     * one can be found.
     */
    public function setNewDefaultSupplierOnProductSupplierConfigurationDeletion(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /** @var string[] $productIdsOfDeletedProductSupplierConfigurations */
        $productIdsOfDeletedProductSupplierConfigurations = (new ImmutableCollection($event->getWriteResults()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE)
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getChangeSet() !== null)
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getChangeSet()->getBefore('supplier_is_default') === '1')
            ->map(fn(EntityWriteResult $writeResult) => bin2hex($writeResult->getChangeSet()->getBefore('product_id')))
            ->asArray();
        if (count($productIdsOfDeletedProductSupplierConfigurations) === 0) {
            return;
        }

        $this->setAlphanumericallyFirstSupplierAsDefaultSupplier($productIdsOfDeletedProductSupplierConfigurations);
        $this->eventDispatcher->dispatch(new DefaultSupplierUpdatedEvent($productIdsOfDeletedProductSupplierConfigurations));
    }

    public function requestChangeSet(EntityPreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (
                $command instanceof DeleteCommand
                && $command->getEntityName(
                ) === ProductSupplierConfigurationDefinition::ENTITY_NAME
            ) {
                $command->requestChangeSet();
            }
        }
    }

    /**
     * REQUIRES: -
     * DOES: Update the default supplier in the pickware products by selecting the "first" supplier.
     *
     * @param string[] $productIds
     */
    private function setAlphanumericallyFirstSupplierAsDefaultSupplier(array $productIds): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($productIds): void {
            $this->connection->executeStatement(
                <<<SQL
                    UPDATE `pickware_erp_pickware_product` AS `pickwareProduct`
                    LEFT JOIN
                    (
                        # When setting a new default supplier for a product, we want to choose the first supplier sorted by
                        # supplier name. For that, we first need to find said supplier configuration for each product.
                        # To that end, we assign a row number to the relevant product supplier configurations grouped by
                        # product and ordered by supplier name.
                        # Subsequently, we select all rows with row number 1, resulting in the supplier configuration with
                        # the first supplier name for each product.
                        SELECT * FROM (
                            SELECT
                                `product`.`id` AS `productId`,
                                `product`.`version_id` AS `productVersionId`,
                                `supplier`.`id` AS `supplierId`,
                                ROW_NUMBER() OVER (PARTITION BY `product`.`id` ORDER BY `supplier`.`name`) AS `rowNumberBySupplierName`
                            FROM `pickware_erp_product_supplier_configuration` AS `productSupplierConfiguration`
                            INNER JOIN `product`
                                ON `productSupplierConfiguration`.`product_id` = `product`.`id`
                                AND `productSupplierConfiguration`.`product_version_id` = `product`.`version_id`
                            INNER JOIN `pickware_erp_supplier` AS `supplier`
                                ON `productSupplierConfiguration`.`supplier_id` = `supplier`.`id`
                            WHERE `productSupplierConfiguration`.`product_id` IN (:productIds)
                        ) AS `productSupplierConfigurationsForProducts`
                        WHERE `rowNumberBySupplierName` = 1
                    ) AS `newProductDefaultSupplierConfigurationsForProducts`
                        ON `pickwareProduct`.`product_id` = `newProductDefaultSupplierConfigurationsForProducts`.`productId`
                        AND `pickwareProduct`.`product_version_id` = `newProductDefaultSupplierConfigurationsForProducts`.`productVersionId`

                    SET `pickwareProduct`.`default_supplier_id` = `newProductDefaultSupplierConfigurationsForProducts`.`supplierId`

                    WHERE `pickwareProduct`.`product_id` IN (:productIds)
                        AND `pickwareProduct`.`product_version_id` = :liveVersionId
                    SQL,
                [
                    'productIds' => array_map('hex2bin', $productIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
                ['productIds' => ArrayParameterType::STRING],
            );
            $this->updateDefaultSupplierFlagOfProductSupplierConfigurations($productIds);
        });
    }

    /**
     * REQUIRES: Default supplier is set in the pickware product.
     * DOES: Update the product supplier configurations according to the pickware product.
     *
     * @param string[] $productIds
     */
    private function updateDefaultSupplierFlagOfProductSupplierConfigurations(array $productIds): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($productIds): void {
            $this->connection->executeStatement(
                <<<SQL
                    UPDATE `pickware_erp_product_supplier_configuration`

                    SET `supplier_is_default` = FALSE

                    WHERE `product_id` IN (:productIds)
                        AND `supplier_is_default` = TRUE;
                    SQL,
                [
                    'productIds' => array_map('hex2bin', $productIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
                ['productIds' => ArrayParameterType::STRING],
            );
            $this->connection->executeStatement(
                <<<SQL
                    UPDATE `pickware_erp_product_supplier_configuration` as `productSupplierConfiguration`
                    INNER JOIN `pickware_erp_pickware_product` as `pickwareProduct`
                        ON `productSupplierConfiguration`.`product_id` = `pickwareProduct`.`product_id`
                        AND `productSupplierConfiguration`.`product_version_id` = `pickwareProduct`.`product_version_id`

                    SET `supplier_is_default` = TRUE

                    WHERE `pickwareProduct`.`default_supplier_id` = `productSupplierConfiguration`.`supplier_id`
                        AND `productSupplierConfiguration`.`product_id` IN (:productIds)
                        AND `productSupplierConfiguration`.`product_version_id` = :liveVersionId
                    SQL,
                [
                    'productIds' => array_map('hex2bin', $productIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
                ['productIds' => ArrayParameterType::STRING],
            );
        });
    }
}
