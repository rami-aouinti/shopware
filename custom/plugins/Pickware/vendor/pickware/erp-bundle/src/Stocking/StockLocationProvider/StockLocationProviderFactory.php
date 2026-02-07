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

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Shopware\Core\Framework\Context;

interface StockLocationProviderFactory
{
    public function makeStockLocationProvider(
        ProductQuantityImmutableCollection $productQuantities,
        StockArea $stockArea,
        Context $context,
    ): StockLocationProvider;
}
