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

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Shopware\Core\Framework\Context;

/**
 * @deprecated Only exists for backwards compatibility with pickware-wms. Will be removed in v5.0.0.
 */
class PickingRequestFactory
{
    public function __construct(
        private readonly OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator,
    ) {}

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link OrderQuantitiesToShipCalculator::calculateProductsToShipForOrders()}` instead.
     *
     * @return array<string, PickingRequest> An array with a PickingRequest for each passed order ID, the key
     *     is the order ID
     */
    public function createPickingRequestsForOrders(array $orderIds, Context $context): array
    {
        return array_map(
            fn(ProductQuantityImmutableCollection $productsToShip) => new PickingRequest(
                productQuantities: $productsToShip,
            ),
            $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrders($orderIds, $context),
        );
    }
}
