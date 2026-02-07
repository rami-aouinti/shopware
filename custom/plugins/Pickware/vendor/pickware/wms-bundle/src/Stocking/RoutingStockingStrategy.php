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

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Routing\RoutingStrategy;
use Pickware\PickwareErpStarter\Stocking\SingleStockLocationStockingStrategy;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProviderFactory;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class RoutingStockingStrategy extends SingleStockLocationStockingStrategy
{
    /**
     * @param StockLocationProviderFactory[] $stockLocationProviderFactories
     */
    public function __construct(
        private readonly RoutingStrategy $routingStrategy,
        array $stockLocationProviderFactories,
    ) {
        parent::__construct($stockLocationProviderFactories);
    }

    public function calculateStockingSolution(
        StockingRequest $stockingRequest,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        return $this->routingStrategy->route(
            productQuantityLocations: parent::calculateStockingSolution($stockingRequest, $context),
            context: $context,
        );
    }
}
