<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The default stock provider for Pickware ERP. It provides stock on all bin locations and the unknown location in the
 * warehouses as pickable stock.
 */
#[AsAlias('pickware_erp.default_pickable_stock_provider')]
class WarehouseAndBinLocationPickableStockProvider implements PickableStockProvider
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getPickableStocks(
        array $productIds,
        ?array $warehouseIds,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        if ($warehouseIds === null) {
            $stockArea = StockArea::everywhere();
        } elseif (count($warehouseIds) === 1) {
            $stockArea = StockArea::warehouse($warehouseIds[0]);
        } else {
            $stockArea = StockArea::warehouses($warehouseIds);
        }

        return $this->getPickableStocksInStockArea($productIds, $stockArea, $context);
    }

    private function getPickableStocksInStockArea(
        array $productIds,
        StockArea $stockArea,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        $stocksOfStockManagedProducts = $this->getStocksOfStockManagedProductsInWarehouses(
            $productIds,
            $stockArea,
            $context,
        );
        $stocksOfNotStockManagedProducts = $this->getStocksOfNotStockManagedProductsInWarehouses(
            $productIds,
            $stockArea,
            $context,
        );

        return $stocksOfStockManagedProducts->merge($stocksOfNotStockManagedProducts);
    }

    /**
     * @param string[] $productIds
     */
    private function getStocksOfStockManagedProductsInWarehouses(
        array $productIds,
        StockArea $stockArea,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        $stockCriteria = new Criteria();
        $stockCriteria->addFilter(
            new EqualsAnyFilter(
                'locationType.technicalName',
                [
                    LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                    LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                ],
            ),
            new EqualsFilter('product.pickwareErpPickwareProduct.isStockManagementDisabled', 0),
            new RangeFilter('quantity', ['gt' => 0]),
            new EqualsAnyFilter('productId', $productIds),
        );

        match ($stockArea->getStockAreaType()) {
            StockAreaType::Warehouse => $stockCriteria->addFilter(
                new MultiFilter('OR', [
                    new EqualsFilter('warehouseId', $stockArea->getWarehouseId()),
                    new EqualsFilter('binLocation.warehouseId', $stockArea->getWarehouseId()),
                ]),
            ),
            StockAreaType::Warehouses => $stockCriteria->addFilter(
                new MultiFilter('OR', [
                    new EqualsAnyFilter('warehouseId', $stockArea->getWarehouseIds()),
                    new EqualsAnyFilter('binLocation.warehouseId', $stockArea->getWarehouseIds()),
                ]),
            ),
            StockAreaType::Everywhere => null,
        };

        /** @var StockCollection $stockEntityCollection */
        $stockEntityCollection = $this->entityManager->findBy(StockDefinition::class, $stockCriteria, $context);

        return $stockEntityCollection->getProductQuantityLocations();
    }

    /**
     * Creates "fake" stock for non stock-managed products
     *
     * Non stock-managed products should always be pickable independent of their actual stock in the warehouse. They
     * also should be picked from the unknown location of a warehouse. This method therefore will return an element
     * with "infinite" stock for each warehouse/product combination.
     *
     * @param string[] $productIds
     */
    private function getStocksOfNotStockManagedProductsInWarehouses(
        array $productIds,
        StockArea $stockArea,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        $notStockManagedProductIds = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            [
                'id' => $productIds,
                'pickwareErpPickwareProduct.isStockManagementDisabled' => 1,
            ],
            $context,
        );

        if (empty($notStockManagedProductIds)) {
            return new ProductQuantityLocationImmutableCollection();
        }

        $warehouseIds = match ($stockArea->getStockAreaType()) {
            StockAreaType::Everywhere => $this->entityManager->findAllIds(WarehouseDefinition::class, $context),
            StockAreaType::Warehouse => [$stockArea->getWarehouseId()],
            StockAreaType::Warehouses => $stockArea->getWarehouseIds(),
        };

        $stocks = [];
        foreach ($notStockManagedProductIds as $productId) {
            foreach ($warehouseIds as $warehouseId) {
                $stocks[] = new ProductQuantityLocation(
                    StockLocationReference::warehouse($warehouseId),
                    $productId,
                    PHP_INT_MAX,
                );
            }
        }

        return new ProductQuantityLocationImmutableCollection($stocks);
    }
}
