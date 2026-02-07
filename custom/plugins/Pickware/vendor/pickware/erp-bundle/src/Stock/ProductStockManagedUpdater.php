<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Product\PickwareProductInsertedEvent;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductStockManagedUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly StockMovementService $stockMovementService,
        private readonly EntityManager $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PickwareProductDefinition::ENTITY_WRITTEN_EVENT => 'pickwareProductWritten',
            PickwareProductInsertedEvent::class => 'pickwareProductInserted',
        ];
    }

    public function pickwareProductWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $pickwareProductStockManageDisabledIds = [];
        $pickwareProductStockManageEnabledIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $operation = $writeResult->getOperation();
            if ($operation === EntityWriteResult::OPERATION_UPDATE) {
                if (
                    ($writeResult->getChangeSet()
                        && $writeResult->getChangeSet()->hasChanged('is_stock_management_disabled'))
                    || isset($writeResult->getPayload()['isStockManagementDisabled'])
                ) {
                    if ($writeResult->getPayload()['isStockManagementDisabled']) {
                        $pickwareProductStockManageDisabledIds[] = $writeResult->getPrimaryKey();
                    } else {
                        $pickwareProductStockManageEnabledIds[] = $writeResult->getPrimaryKey();
                    }
                }
            }
        }

        $this->moveStockFromBinLocationsToWarehouse($pickwareProductStockManageDisabledIds, $event->getContext());

        $this->moveStockFromExternalToWarehouse($pickwareProductStockManageEnabledIds, $event->getContext());
    }

    // Check if parent product of new inserted pickware product is not stock managed. If the parent is not stock managed
    // we need to set the new created pickware product to non stock managed
    public function pickwareProductInserted(PickwareProductInsertedEvent $event): void
    {
        $context = $event->getContext();

        $productCriteria = new Criteria();
        $productCriteria->addFilter(new EqualsAnyFilter('id', array_unique($event->getProductIds())));
        $productCriteria->addFilter(new NotFilter('OR', [
            new EqualsFilter('parentId', null),
        ]));

        /** @var ProductCollection $variantProducts */
        $variantProducts = $this->entityManager->findBy(
            ProductDefinition::class,
            $productCriteria,
            $context,
            [
                'pickwareErpPickwareProduct',
            ],
        );

        if ($variantProducts->count() === 0) {
            return;
        }

        $variantPickwareProductIdsByParentProductIds = [];
        foreach ($variantProducts as $variantProduct) {
            $variantPickwareProductIdsByParentProductIds[$variantProduct->getParentId()] = $variantProduct
                ->getExtension('pickwareErpPickwareProduct')
                ->getId();
        }

        // As we can not fetch the associative parents of the product ids provided, we need to fetch them separately.
        /** @var ProductCollection $variantProducts */
        $parentPickwareProductsByVariants = $this->entityManager->findBy(
            ProductDefinition::class,
            [
                'id' => array_keys($variantPickwareProductIdsByParentProductIds),
                'pickwareErpPickwareProduct.isStockManagementDisabled' => true,
            ],
            $context,
        );

        $variantPickwareProductsToBeUpdated = [];
        foreach ($parentPickwareProductsByVariants->getElements() as $parentProduct) {
            $variantPickwareProductsToBeUpdated[] = [
                'id' => $variantPickwareProductIdsByParentProductIds[$parentProduct->getId()],
                'isStockManagementDisabled' => true,
            ];
        }

        $this->entityManager->update(
            PickwareProductDefinition::class,
            $variantPickwareProductsToBeUpdated,
            $context,
        );
    }

    public function applyStockManagementFromParentsToVariants(array $productIds, bool $isStockManagementDisabled, Context $context): void
    {
        $variantPickwareProductsToBeUpdated = [];

        /** @var ProductEntity $product */
        $variantProducts = $this->entityManager->findBy(
            ProductDefinition::class,
            [
                'parentId' => $productIds,
            ],
            $context,
            [
                'pickwareErpPickwareProduct',
            ],
        );

        foreach ($variantProducts as $variantProduct) {
            $variantPickwareProductsToBeUpdated[] = [
                'id' => $variantProduct->getExtension('pickwareErpPickwareProduct')->getId(),
                'isStockManagementDisabled' => $isStockManagementDisabled,
            ];
        }

        $this->entityManager->update(
            PickwareProductDefinition::class,
            $variantPickwareProductsToBeUpdated,
            $context,
        );
    }

    // Move all the stock that is still in bin locations to their respective warehouse as we do not track the stock of
    // the product anymore
    public function moveStockFromBinLocationsToWarehouse(array $pickwareProductIds, Context $context): void
    {
        if (count($pickwareProductIds) === 0) {
            return;
        }

        /** @var ProductEntity $product */
        $productIds = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            [
                'product.pickwareErpPickwareProduct.id' => $pickwareProductIds,
            ],
            $context,
        );

        /** @var StockCollection $stocks */
        $stocks = $this->entityManager->findBy(
            StockDefinition::class,
            [
                'productId' => $productIds,
                'locationType.technicalName' => LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
            ],
            $context,
            [
                'product',
                'binLocation',
            ],
        );

        // Be sure to set closeout sale to false for these products
        $this->db->executeStatement(
            'UPDATE `product`
            SET `is_closeout` = 0
            WHERE `id` IN (:productId) AND `version_id` = (:liveVersionId)',
            [
                'productId' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productId' => ArrayParameterType::STRING,
            ],
        );

        $this->eventDispatcher->dispatch(new ProductAvailableStockUpdatedEvent($productIds, $context));

        // Remove default bin location configuration for products that are not stock managed anymore. Use the dal for
        // this explicitly so that subscribers can react to it.
        $productWarehouseConfigurationCriteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('productId', $productIds))
            ->addFilter(new NotFilter('AND', [
                new EqualsFilter('defaultBinLocationId', null),
            ]));
        $productWarehouseConfigurationIds = $this->entityManager->findIdsBy(
            ProductWarehouseConfigurationDefinition::class,
            $productWarehouseConfigurationCriteria,
            $context,
        );
        $this->entityManager->update(
            ProductWarehouseConfigurationDefinition::class,
            array_map(
                fn(string $warehouseConfigurationId) => [
                    'id' => $warehouseConfigurationId,
                    'defaultBinLocationId' => null,
                ],
                $productWarehouseConfigurationIds,
            ),
            $context,
        );

        $stockMovements = [];
        foreach ($stocks as $stock) {
            if ($stock->getQuantity() === 0) {
                // Stock in default bin location may be 0. No stock movement needs to be written.
                continue;
            }
            $stockMovements[] = StockMovement::create([
                'productId' => $stock->getProductId(),
                'source' => StockLocationReference::binLocation($stock->getBinLocationId()),
                'destination' => StockLocationReference::warehouse($stock->getBinLocation()->getWarehouseId()),
                'quantity' => $stock->getQuantity(),
            ]);
        }

        $this->stockMovementService->moveStock($stockMovements, $context);
        $this->applyStockManagementFromParentsToVariants($productIds, true, $context);
    }

    // After the stock management is turned on again, we need to make sure, that there is no negative stock in the
    // warehouses. Therefore, we move the positive equivalent to the negative quantity from unknown into the warehouses
    public function moveStockFromExternalToWarehouse(array $pickwareProductIds, Context $context): void
    {
        if (count($pickwareProductIds) === 0) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.pickwareErpPickwareProduct.id', $pickwareProductIds));
        $criteria->addFilter(new EqualsAnyFilter('locationType.technicalName', [
            LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
            LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
        ]));
        $criteria->addFilter(new RangeFilter('quantity', ['lt' => 0]));

        /** @var StockCollection $stocks */
        $stocks = $this->entityManager->findBy(
            StockDefinition::class,
            $criteria,
            $context,
            [
                'locationType',
            ],
        );

        $stockMovements = [];
        foreach ($stocks->getElements() as $stock) {
            // check whether the stock location is a bin location or warehouse and set it as destination
            $destination = null;
            if ($stock->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE) {
                $destination = StockLocationReference::warehouse($stock->getWarehouseId());
            }

            if ($stock->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION) {
                $destination = StockLocationReference::binLocation($stock->getBinLocationId());
            }

            if (isset($destination)) {
                $stockMovements[] = StockMovement::create([
                    'productId' => $stock->getProductId(),
                    'source' => StockLocationReference::unknown(),
                    'destination' => $destination,
                    'quantity' => -1 * $stock->getQuantity(),
                ]);
            }
        }

        if (count($stockMovements) !== 0) {
            $this->stockMovementService->moveStock($stockMovements, $context);
        }

        $this->applyStockManagementFromParentsToVariants(
            $this->entityManager->findIdsBy(
                ProductDefinition::class,
                [
                    'pickwareErpPickwareProduct.id' => $pickwareProductIds,
                ],
                $context,
            ),
            false,
            $context,
        );
    }
}
