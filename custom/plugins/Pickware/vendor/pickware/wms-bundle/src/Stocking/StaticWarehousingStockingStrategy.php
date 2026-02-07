<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Stocking;

use Pickware\PickwareErpStarter\Routing\RoutingStrategy;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\UnknownBinLocationProviderFactory;
use Pickware\PickwareWms\Stocking\StockLocationProvider\BinLocationWithStockProviderFactory;
use Pickware\PickwareWms\Stocking\StockLocationProvider\DisabledStockManagementLocationProviderFactory;
use Pickware\PickwareWms\Stocking\StockLocationProvider\EmptyBinLocationProviderFactory;
use Pickware\PickwareWms\Stocking\StockLocationProvider\WmsDefaultBinLocationProviderFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Uses the following priorities to gather a fitting stock location for each product:
 *
 * Priority 1: Use default bin location
 * Priority 2: Use a bin location with the same product
 * Priority 3: Use an empty bin location
 * Priority 4: Use unknown bin location
 */
class StaticWarehousingStockingStrategy extends RoutingStockingStrategy
{
    public function __construct(
        #[Autowire(service: 'pickware_erp.default_routing_strategy')]
        RoutingStrategy $routingStrategy,
        DisabledStockManagementLocationProviderFactory $disabledStockManagementLocationProviderFactory,
        WmsDefaultBinLocationProviderFactory $defaultBinLocationProviderFactory,
        BinLocationWithStockProviderFactory $binLocationWithStockProviderFactory,
        EmptyBinLocationProviderFactory $emptyBinLocationProviderFactory,
        UnknownBinLocationProviderFactory $unknownBinLocationProviderFactory,
    ) {
        parent::__construct(
            routingStrategy: $routingStrategy,
            stockLocationProviderFactories: [
                $disabledStockManagementLocationProviderFactory,
                $defaultBinLocationProviderFactory,
                $binLocationWithStockProviderFactory,
                $emptyBinLocationProviderFactory,
                $unknownBinLocationProviderFactory,
            ],
        );
    }
}
