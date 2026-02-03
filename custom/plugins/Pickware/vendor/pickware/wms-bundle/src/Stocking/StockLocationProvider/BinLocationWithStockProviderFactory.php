<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Stocking\StockLocationProvider;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\BatchFeatureService;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\ProductStockLocationMap;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProvider;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProviderFactory;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @deprecated replaced by BatchPreservingBinLocationWithStockProviderFactory. Can be removed as soon as the minimum
 * required ERP version guarantees that the batch preserving factory is available.
 */
class BinLocationWithStockProviderFactory implements StockLocationProviderFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'pickware_wms.bin_location_property_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $stockLocationSorter,
        // Might be null with old ERP versions
        private readonly ?BatchFeatureService $batchFeatureService = null,
        private readonly ?BatchPreservingBinLocationWithStockProviderFactory $batchPreservingBinLocationWithStockProviderFactory = null,
    ) {}

    public function makeStockLocationProvider(
        ProductQuantityImmutableCollection $productQuantities,
        StockArea $stockArea,
        Context $context,
    ): StockLocationProvider {
        if (
            $this->batchFeatureService?->isBatchManagementAvailable()
            && $this->batchPreservingBinLocationWithStockProviderFactory !== null
        ) {
            return $this->batchPreservingBinLocationWithStockProviderFactory->makeStockLocationProvider(
                $productQuantities,
                $stockArea,
                $context,
            );
        }

        $criteria = ['stocks.productId' => $productQuantities->getProductIds()->asArray()];
        match ($stockArea->getStockAreaType()) {
            StockAreaType::Warehouse => $criteria['warehouseId'] = $stockArea->getWarehouseId(),
            StockAreaType::Warehouses => $criteria['warehouseId'] = $stockArea->getWarehouseIds(),
            StockAreaType::Everywhere => null,
        };
        // Step 1: Find bin locations that contain the same products
        /** @var ImmutableCollection<BinLocationEntity> $binLocationsWithProducts */
        $binLocationsWithProducts = ImmutableCollection::create($this->entityManager->findBy(
            BinLocationDefinition::class,
            $criteria,
            $context,
            ['stocks'],
        ));

        // Step 2: Sort the bin location references and apply that sorting to the bin locations.
        $sortedStockLocations = $this->stockLocationSorter->sort(
            stockLocationReferences: $binLocationsWithProducts->map(
                fn(BinLocationEntity $binLocation) => StockLocationReference::binLocation($binLocation->getId()),
            ),
            context: $context,
        );
        $prioritizedBinLocationsWithProducts = $binLocationsWithProducts->sorted(
            function(BinLocationEntity $lhs, BinLocationEntity $rhs) use ($sortedStockLocations) {
                $lhsIndex = $sortedStockLocations->indexOfElementEqualTo(StockLocationReference::binLocation($lhs->getId()));
                $rhsIndex = $sortedStockLocations->indexOfElementEqualTo(StockLocationReference::binLocation($rhs->getId()));

                return $lhsIndex <=> $rhsIndex;
            },
        );

        $prioritizedBinLocationsByProductId = [];
        foreach ($productQuantities->asArray() as $productQuantity) {
            $productId = $productQuantity->getProductId();
            $selectedBinLocation = $prioritizedBinLocationsWithProducts->first(
                fn(BinLocationEntity $binLocation) => $binLocation->getStocks()->getProductQuantities()->containsElementSatisfying(
                    fn(ProductQuantity $stock) => $stock->getProductId() === $productId,
                ),
            );
            if ($selectedBinLocation !== null) {
                $prioritizedBinLocationsByProductId[$productId] = StockLocationReference::binLocation($selectedBinLocation->getId());
            }
        }

        return new ProductStockLocationMap($prioritizedBinLocationsByProductId);
    }
}
