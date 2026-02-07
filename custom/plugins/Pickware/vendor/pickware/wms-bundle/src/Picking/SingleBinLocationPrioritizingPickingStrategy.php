<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Picking;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Picking\PickableStockProvider;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategy;
use Pickware\PickwareErpStarter\Picking\PickingStrategyService;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Routing\RoutingStrategy;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias('pickware_wms.default_picking_strategy')]
class SingleBinLocationPrioritizingPickingStrategy implements PickingStrategy
{
    public function __construct(
        private readonly PickingStrategyService $pickingStrategyService,
        #[Autowire(service: 'pickware_erp.default_routing_strategy')]
        private readonly RoutingStrategy $routingStrategy,
        #[Autowire(service: 'pickware_wms.default_pickable_stock_provider')]
        private readonly PickableStockProvider $pickableStockProvider,
        #[Autowire(service: 'pickware_wms.bin_location_property_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $stockLocationSorter,
    ) {}

    public function calculatePickingSolution(
        PickingRequest $pickingRequest,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        // Step 1: Get all necessary data
        if (method_exists($pickingRequest->getSourceStockArea(), 'getWarehouseIds')) {
            $warehouseIds = match ($pickingRequest->getSourceStockArea()->getStockAreaType()) {
                StockAreaType::Warehouse => [$pickingRequest->getSourceStockArea()->getWarehouseId()],
                StockAreaType::Warehouses => $pickingRequest->getSourceStockArea()->getWarehouseIds(),
                StockAreaType::Everywhere => null,
            };
        } else {
            // Fallback behaviour, can be removed when the minimum required version for Pickware ERP Starter is 4.10.1 or higher.
            $warehouseIds = match ($pickingRequest->getSourceStockArea()->getStockAreaType()) {
                StockAreaType::Warehouse => [$pickingRequest->getSourceStockArea()->getWarehouseId()],
                StockAreaType::Everywhere => null,
            };
        }
        $productsToPick = $pickingRequest->getProductsToPick()->groupByProductId();
        /** @var ProductQuantityLocationImmutableCollection $pickableStock */
        $pickableStock = $this->pickableStockProvider->getPickableStocks(
            $productsToPick->getProductIds()->asArray(),
            $warehouseIds,
            $context,
        );

        // Step 2: Prioritize bin locations that can fulfill the products to pick at one location.
        $preferredStock = $pickableStock
            ->filter(function(ProductQuantityLocation $stock) use ($productsToPick) {
                $quantityToPick = $productsToPick->first(
                    fn(ProductQuantity $productQuantity) => $productQuantity->getProductId() === $stock->getProductId(),
                )?->getQuantity() ?? 0;

                return
                    $stock->getQuantity() >= $quantityToPick
                    && $stock->getStockLocationReference()->isBinLocation();
            });
        $undesirableStock = $pickableStock->getElementsNotIdenticallyContainedIn($preferredStock);

        // Step 3: Sort the stock locations
        $sortedPreferredStockLocations = $this->stockLocationSorter->sort(
            stockLocationReferences: $preferredStock
                ->map(fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()),
            context: $context,
        );
        $sortedUndesirableStockLocations = $this->stockLocationSorter->sort(
            stockLocationReferences: $undesirableStock
                ->map(fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()),
            context: $context,
        );

        $sortedPreferredStock = $preferredStock->sortLike($sortedPreferredStockLocations);
        $sortedUndesirableStock = $undesirableStock->sortLike($sortedUndesirableStockLocations);

        // Step 4: Select locations to pick from
        try {
            $locationsToPickFrom = $this->pickingStrategyService->selectLocationsToPickFrom(
                prioritizedStock: $sortedPreferredStock->merge($sortedUndesirableStock),
                productsToPick: $pickingRequest->getProductsToPick(),
                context: $context,
            );
        } catch (PickingStrategyStockShortageException $exception) {
            // Step 5: Apply a routing through the warehouse for the partial picking request solution.
            $productNumbers = match (method_exists($exception, 'getProductNumbers')) {
                true => $exception->getProductNumbers(),
                false => $exception->serializeToJsonApiError()->getMeta()['productNumbers'] ?? [],
            };

            throw new PickingStrategyStockShortageException(
                stockShortages: $exception->getStockShortages(),
                partialPickingRequestSolution: $this->routingStrategy->route(
                    $exception->getPartialPickingRequestSolution(),
                    $context,
                ),
                productNumbers: $productNumbers,
            );
        }

        // Step 5: Apply a routing through the warehouse for the selected locations.
        return $this->routingStrategy->route($locationsToPickFrom, $context);
    }
}
