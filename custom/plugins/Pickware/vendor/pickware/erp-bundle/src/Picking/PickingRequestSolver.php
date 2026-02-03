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
use Pickware\PickwareErpStarter\Routing\RoutingStrategy;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\StockApi\StockLocationConfigurationService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationProperty;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Shopware\Core\Framework\Context;

/**
 * @deprecated Only exists for backwards compatibility with pickware-wms. Will be removed in v5.0.0.
 */
class PickingRequestSolver
{
    private AlphanumericalPickingStrategy $legacyPickingStrategy;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly PickingStrategy $pickingStrategy,
        private readonly RoutingStrategy $routingStrategy,
        private readonly PickableStockProvider $pickableStockProvider,
    ) {
        $this->legacyPickingStrategy = new AlphanumericalPickingStrategy(
            entityManager: $entityManager,
            pickableStockProvider: $pickableStockProvider,
            pickingStrategyService: new PickingStrategyService($entityManager),
            stockLocationSorter: new BinLocationPropertyStockLocationSorter(
                stockLocationConfigurationService: new StockLocationConfigurationService($entityManager),
                sortByProperties: [BinLocationProperty::Code],
            ),
        );
    }

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link PickingStrategy::calculatePickingSolution()}` instead.
     */
    public function solvePickingRequestInWarehouses(
        PickingRequest &$pickingRequest,
        ?array $warehouseIds,
        Context $context,
    ): PickingRequest {
        $newPickingRequest = new PickingRequest(
            productQuantities: $pickingRequest->getProductsToPick(),
            sourceStockArea: $warehouseIds ? StockArea::warehouse($warehouseIds[0]) : StockArea::everywhere(),
        );

        try {
            $pickingSolution = $this->routingStrategy->route(
                $this->legacyPickingStrategy->calculatePickingSolution(
                    pickingRequest: $newPickingRequest,
                    context: $context,
                ),
                $context,
            );
        } catch (PickingStrategyStockShortageException $exception) {
            $pickingSolution = $exception->getPartialPickingRequestSolution();
        }

        // Apply the sorting of routed solution and apply it to the original picking request
        $sortedProductQuantities = $pickingSolution->map(
            fn(ProductQuantityLocation $solution) => new ProductQuantity(
                productId: $solution->getProductId(),
                quantity: $solution->getQuantity(),
            ),
        );
        $sortedProductsToPick = $newPickingRequest->getProductsToPick()->sorted(
            function(ProductQuantity $lhs, ProductQuantity $rhs) use ($sortedProductQuantities) {
                $lhsIndex = $sortedProductQuantities->indexOfElementEqualTo($lhs);
                $rhsIndex = $sortedProductQuantities->indexOfElementEqualTo($rhs);

                return $lhsIndex <=> $rhsIndex;
            },
        );

        $sortedPickingRequest = new PickingRequest(
            productQuantities: $sortedProductsToPick,
            sourceStockArea: $newPickingRequest->getSourceStockArea(),
            legacyPickingRequestSolution: $pickingSolution->asArray(),
        );

        // There are `pickware-wms` versions that depend on the passed picking request to be mutated. That's why we
        // override the original picking request with the sorted one.
        $pickingRequest = $sortedPickingRequest;

        return $sortedPickingRequest;
    }
}
