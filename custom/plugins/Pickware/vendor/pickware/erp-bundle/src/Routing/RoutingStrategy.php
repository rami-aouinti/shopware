<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Routing;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Shopware\Core\Framework\Context;

interface RoutingStrategy
{
    /**
     * Calculate the optimal route for the given combinations of products and locations.
     *
     * Remember that the calculated route may not be the shortest one, but it may be optimal one. Therefore, the
     * products and their quantities are also passed to be able to respect the weight or volume of a product.
     *
     * Remember to also sort the unknown location of the warehouse.
     */
    public function route(
        ProductQuantityLocationImmutableCollection $productQuantityLocations,
        Context $context,
    ): ProductQuantityLocationImmutableCollection;
}
