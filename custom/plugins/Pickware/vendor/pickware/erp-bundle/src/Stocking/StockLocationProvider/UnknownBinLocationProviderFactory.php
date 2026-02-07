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

use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Shopware\Core\Framework\Context;

class UnknownBinLocationProviderFactory implements StockLocationProviderFactory
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function makeStockLocationProvider(
        ProductQuantityImmutableCollection $productQuantities,
        StockArea $stockArea,
        Context $context,
    ): StaticStockLocationProvider {
        $stockLocationReference = match ($stockArea->getStockAreaType()) {
            StockAreaType::Warehouse => StockLocationReference::warehouse($stockArea->getWarehouseId()),
            StockAreaType::Everywhere => StockLocationReference::warehouse($this->config->getDefaultWarehouseId()),
        };

        return new StaticStockLocationProvider($stockLocationReference);
    }
}
