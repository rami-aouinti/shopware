<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking\StockLocationProvider;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DefaultBinLocationProviderFactory implements StockLocationProviderFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'pickware_erp.bin_location_code_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $binLocationPropertyStockLocationSorter,
    ) {}

    public function makeStockLocationProvider(
        ProductQuantityImmutableCollection $productQuantities,
        StockArea $stockArea,
        Context $context,
    ): ProductStockLocationMap {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter(
            field: 'productId',
            value: $productQuantities->getProductIds()->asArray(),
        ));
        $criteria->addFilter(new NandFilter(queries: [new EqualsFilter(field: 'defaultBinLocationId', value: null)]));

        match ($stockArea->getStockAreaType()) {
            StockAreaType::Warehouse => $criteria->addFilter(
                new EqualsFilter(field: 'warehouseId', value: $stockArea->getWarehouseId()),
            ),
            StockAreaType::Everywhere => null,
        };

        $productWarehouseConfigurations = new ImmutableCollection($this->entityManager->findBy(
            ProductWarehouseConfigurationDefinition::class,
            $criteria,
            $context,
        ));
        $prioritizedStockLocations = $this->binLocationPropertyStockLocationSorter->sort(
            $productWarehouseConfigurations->map(
                fn(ProductWarehouseConfigurationEntity $productWarehouseConfiguration) => StockLocationReference::binLocation(
                    $productWarehouseConfiguration->getDefaultBinLocationId(),
                ),
            ),
            context: $context,
        );

        // Apply the reversed sorting of the stock locations to the product warehouse configurations so the most
        // important stock locations are at the end of the list. If there are multiple stock locations for each product,
        // the last stock location will be preserved after array_combine.
        $sortedProductWarehouseConfigurations = $productWarehouseConfigurations->sorted(
            function(ProductWarehouseConfigurationEntity $lhs, ProductWarehouseConfigurationEntity $rhs) use ($prioritizedStockLocations) {
                $lhsIndex = $prioritizedStockLocations->indexOfElementEqualTo(
                    StockLocationReference::binLocation($lhs->getDefaultBinLocationId()),
                );
                $rhsIndex = $prioritizedStockLocations->indexOfElementEqualTo(
                    StockLocationReference::binLocation($rhs->getDefaultBinLocationId()),
                );

                return $rhsIndex <=> $lhsIndex;
            },
        );

        return new ProductStockLocationMap(array_combine(
            $sortedProductWarehouseConfigurations
                ->map(
                    fn(ProductWarehouseConfigurationEntity $productWarehouseConfiguration) => $productWarehouseConfiguration->getProductId(),
                )
                ->asArray(),
            $sortedProductWarehouseConfigurations
                ->map(
                    fn(ProductWarehouseConfigurationEntity $productWarehouseConfiguration) => StockLocationReference::binLocation(
                        $productWarehouseConfiguration->getDefaultBinLocationId(),
                    ),
                )
                ->asArray(),
        ));
    }
}
