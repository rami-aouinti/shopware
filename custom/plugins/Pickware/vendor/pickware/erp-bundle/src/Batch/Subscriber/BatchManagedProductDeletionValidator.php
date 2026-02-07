<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\BatchManagedProductDeletionException;
use Pickware\PickwareErpStarter\Batch\BatchManagementDevFeatureFlag;
use Pickware\PickwareErpStarter\Batch\BatchManagementProdFeatureFlag;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductCollection;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BatchManagedProductDeletionValidator implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteValidationEventType::Pre->getEventName(ProductDefinition::ENTITY_NAME) => 'validateDeletionOfBatchManagedProducts',
        ];
    }

    public function validateDeletionOfBatchManagedProducts(EntityPreWriteValidationEvent $event): void
    {
        if (
            !$this->featureFlagService->isActive(BatchManagementDevFeatureFlag::NAME)
            || !$this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)
        ) {
            return;
        }
        if ($event->getContext()->getScope() !== Context::CRUD_API_SCOPE) {
            return;
        }

        $productIdsToValidate = ImmutableCollection::fromArray($event->getCommands())
            ->filter(fn(WriteCommand $command) => $command instanceof DeleteCommand)
            ->filter(fn(DeleteCommand $command) => isset($command->getPrimaryKey()['id']))
            ->map(fn(DeleteCommand $command) => bin2hex($command->getPrimaryKey()['id']))
            ->deduplicate();
        if ($productIdsToValidate->isEmpty()) {
            return;
        }

        /** @var PickwareProductCollection $batchManagedProducts */
        $batchManagedProducts = $this->entityManager->findBy(
            PickwareProductDefinition::class,
            [
                'productId' => $productIdsToValidate->asArray(),
                'isBatchManaged' => true,
            ],
            $event->getContext(),
            ['product'],
        );
        $affectedProductNumbers = ImmutableCollection::create($batchManagedProducts)
            ->map(fn(PickwareProductEntity $pickwareProduct) => $pickwareProduct->getProduct()->getProductNumber())
            ->deduplicate();
        if ($affectedProductNumbers->isEmpty()) {
            return;
        }

        throw BatchManagedProductDeletionException::batchManagedProductsCannotBeDeleted($affectedProductNumbers->asArray());
    }
}
