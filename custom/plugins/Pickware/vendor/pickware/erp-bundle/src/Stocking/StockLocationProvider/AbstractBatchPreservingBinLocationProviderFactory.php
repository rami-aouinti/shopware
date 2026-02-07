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
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\BatchManagementProdFeatureFlag;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

abstract class AbstractBatchPreservingBinLocationProviderFactory implements StockLocationProviderFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
        #[Autowire(service: 'pickware_erp.bin_location_code_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $binLocationPropertyStockLocationSorter,
    ) {}

    /**
     * Creates a criteria selecting bin locations that can stock the products from the given product quantities.
     * Make sure to also add all associations required in {@link self::checkBinLocationCanStockProduct()} here.
     */
    abstract public function createBinLocationCriteria(ProductQuantityImmutableCollection $productQuantities): Criteria;

    /**
     * Checks whether the bin location can stock the product. Make sure to add all bin location associations
     * required for this function in {@link self::createBinLocationCriteria()}.
     */
    abstract public function checkBinLocationCanStockProduct(BinLocationEntity $binLocation, string $productId): bool;

    public function makeStockLocationProvider(ProductQuantityImmutableCollection $productQuantities, StockArea $stockArea, Context $context): BatchAwareStockLocationProvider
    {
        // 1. Find all bin locations relevant for this factory and the given stock area.
        $criteria = $this->createBinLocationCriteria($productQuantities);
        match ($stockArea->getStockAreaType()) {
            StockAreaType::Warehouse => $criteria->addFilter(new EqualsFilter('warehouseId', $stockArea->getWarehouseId())),
            StockAreaType::Warehouses => $criteria->addFilter(new EqualsAnyFilter('warehouseId', $stockArea->getWarehouseIds())),
            StockAreaType::Everywhere => null,
        };
        $criteria->addAssociation('stocks.batchMappings');
        /** @var ImmutableCollection<BinLocationEntity> $binLocations */
        $binLocations = ImmutableCollection::create($this->entityManager->findBy(
            BinLocationDefinition::class,
            $criteria,
            $context,
        ));

        // 2. Sort the bin locations according to the sorters priority.
        $prioritizedBinLocationIds = $this->binLocationPropertyStockLocationSorter
            ->sort(
                $binLocations->map(fn(BinLocationEntity $binLocation) => StockLocationReference::binLocation($binLocation->getId())),
                $context,
            )
            ->map(fn(StockLocationReference $stockLocationReference) => $stockLocationReference->getBinLocationId());
        $binLocationPriorities = array_flip($prioritizedBinLocationIds->asArray());
        $prioritizedBinLocations = $binLocations->sorted(
            fn(BinLocationEntity $lhs, BinLocationEntity $rhs) => $binLocationPriorities[$lhs->getId()] <=> $binLocationPriorities[$rhs->getId()],
        );

        // 3. For each product quantity, find all bin locations that could stock that product and add them to the list, preserving the previous sorting.
        $productStockLocations = [];
        foreach ($productQuantities as $productQuantity) {
            $productId = $productQuantity->getProductId();
            $productStockLocations[$productId] = $prioritizedBinLocations
                ->filter(fn(BinLocationEntity $binLocation) => $this->checkBinLocationCanStockProduct($binLocation, $productId))
                ->map(function(BinLocationEntity $binLocation) use ($productId) {
                    // 3.1. For each bin location, populate the stock location information with the current stock.
                    $productStock = $binLocation->getStockForProduct($productId);
                    if ($productStock === null) {
                        return new StockLocationBatchInformation(
                            location: StockLocationReference::binLocation($binLocation->getId()),
                            batchIds: [],
                            hasStockWithoutBatchInformation: false,
                        );
                    }

                    $batchQuantities = $productStock->getBatchMappings()->asBatchCountingMap();

                    return new StockLocationBatchInformation(
                        location: StockLocationReference::binLocation($binLocation->getId()),
                        batchIds: $batchQuantities->getKeys(),
                        hasStockWithoutBatchInformation: $batchQuantities->getTotalCount() < $productStock->getQuantity(),
                    );
                })
                ->asArray();
        }

        // 4. Collect the IDs of all batch-managed products. If the feature flag is disabled, treat all products as not batch-managed.
        $batchManagedProductIds = [];
        if ($this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)) {
            $batchManagedProductIds = $this->entityManager->findIdsBy(
                ProductDefinition::class,
                [
                    'id' => $productQuantities->getProductIds()->asArray(),
                    'pickwareErpPickwareProduct.isBatchManaged' => true,
                ],
                $context,
            );
        }

        return new BatchPreservingProductStockLocationsMap(
            stockLocationsByProductId: array_filter($productStockLocations),
            batchManagedProductIds: $batchManagedProductIds,
        );
    }
}
