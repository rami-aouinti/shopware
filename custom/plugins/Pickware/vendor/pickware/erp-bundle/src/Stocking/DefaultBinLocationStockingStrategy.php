<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking;

use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Routing\RoutingStrategy;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\DefaultBinLocationProviderFactory;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\UnknownBinLocationProviderFactory;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

// This protocol alias is needed for backwards compatibility with Pickware WMS and can be removed in v5.0.0
#[AsAlias(StockingStrategy::class)]
#[AsAlias('pickware_erp.stocking.default_stocking_strategy')]
class DefaultBinLocationStockingStrategy implements ProductOrthogonalStockingStrategy
{
    private readonly SingleStockLocationStockingStrategy $singleStockLocationStockingStrategy;

    public function __construct(
        private readonly RoutingStrategy $routingStrategy,
        private readonly Config $config,
        DefaultBinLocationProviderFactory $defaultBinLocationProviderFactory,
        UnknownBinLocationProviderFactory $unknownBinLocationProviderFactory,
    ) {
        $this->singleStockLocationStockingStrategy = new SingleStockLocationStockingStrategy([
            $defaultBinLocationProviderFactory,
            $unknownBinLocationProviderFactory,
        ]);
    }

    /**
     * @return ProductQuantityLocationImmutableCollection the solution is only routed for backwards compatibility with
     * pickware-wms. It will not be routed anymore from v5.0.0 going forward.
     */
    public function calculateStockingSolution(
        StockingRequest $stockingRequest,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        // The default stocking strategy always stocks in a single warehouse. In case no warehouse was provided, the
        // erp default warehouse is used.
        if ($stockingRequest->getStockArea()->isWarehouse()) {
            $warehouseId = $stockingRequest->getStockArea()->getWarehouseId();
        }
        $warehouseId ??= $this->config->getDefaultWarehouseId();
        $stockingRequestInSingleWarehouse = new StockingRequest(
            productQuantities: $stockingRequest->getProductQuantities(),
            stockArea: StockArea::warehouse($warehouseId),
        );

        return $this->routingStrategy->route(
            $this->singleStockLocationStockingStrategy->calculateStockingSolution(
                $stockingRequestInSingleWarehouse,
                $context,
            ),
            $context,
        );
    }
}
