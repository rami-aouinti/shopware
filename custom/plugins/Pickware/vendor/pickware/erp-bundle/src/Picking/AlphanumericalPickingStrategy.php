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
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias('pickware_erp.default_picking_strategy')]
class AlphanumericalPickingStrategy implements ProductOrthogonalPickingStrategy
{
    public function __construct(
        readonly private EntityManager $entityManager,
        readonly private PickableStockProvider $pickableStockProvider,
        readonly private PickingStrategyService $pickingStrategyService,
        #[Autowire(service: 'pickware_erp.bin_location_code_stock_location_sorter')]
        readonly private BinLocationPropertyStockLocationSorter $stockLocationSorter,
    ) {}

    public function calculatePickingSolution(
        PickingRequest $pickingRequest,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        // Step 1: Get all necessary data
        $warehouseIds = match ($pickingRequest->getSourceStockArea()->getStockAreaType()) {
            StockAreaType::Warehouse => [$pickingRequest->getSourceStockArea()->getWarehouseId()],
            StockAreaType::Warehouses => $pickingRequest->getSourceStockArea()->getWarehouseIds(),
            StockAreaType::Everywhere => null,
        };
        $pickableStock = $this->pickableStockProvider->getPickableStocks(
            $pickingRequest->getProductsToPick()->getProductIds()->asArray(),
            $warehouseIds,
            $context,
        );
        $pickableStock = ProductQuantityLocationImmutableCollection::create($pickableStock->asArray());

        // Step 2: Sort the pickable stock by stock locations to prioritize for picking
        $sortedStockLocations = $this->stockLocationSorter->sort(
            stockLocationReferences: $pickableStock->map(
                fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference(),
            ),
            context: $context,
        );
        $sortedPickableStock = $pickableStock->sortLike($sortedStockLocations);

        // Step 3: Select locations to pick from
        return $this->pickingStrategyService->selectLocationsToPickFrom(
            prioritizedStock: $sortedPickableStock,
            productsToPick: $pickingRequest->getProductsToPick(),
            context: $context,
        );
    }
}
