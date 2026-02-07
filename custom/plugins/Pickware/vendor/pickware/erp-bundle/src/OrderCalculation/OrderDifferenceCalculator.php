<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderCalculation;

use Shopware\Core\Framework\Context;

class OrderDifferenceCalculator
{
    private OrderCalculationService $orderCalculationService;
    private CalculatableOrderFactory $orderFactory;

    public function __construct(
        OrderCalculationService $orderCalculationService,
        CalculatableOrderFactory $orderFactory,
    ) {
        $this->orderCalculationService = $orderCalculationService;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Calculates the order difference between two versions of the same order.
     *
     * An order difference is defined by a "new" order at a new version and an "old" order at an old version. It is
     * calculated by subtracting the old orders and its return orders from the new order and its return orders.
     * In other words: The order difference is "what has changed" from the old order to the new order while considering
     * all return orders.
     *
     * Not that many non-calculatable properties (e.g. customer, addresses, etc.) are not differentiated here but simply
     * kept from the new order. Any other values from the old order will be ignored in this case.
     */
    public function calculateOrderDifference(
        string $orderId,
        string $oldVersionId,
        string $newVersionId,
        Context $context,
    ): CalculatableOrder {
        $oldVersionContext = $context->createWithVersionId($oldVersionId);
        $oldOrder = $this->orderFactory->createCalculatableOrderFromOrder($orderId, $oldVersionContext);
        $oldReturnOrdersAsOrders = $this->orderFactory->createCalculatableOrdersFromReturnOrdersOfOrder($orderId, $oldVersionContext);

        $newVersionContext = $context->createWithVersionId($newVersionId);
        $newOrder = $this->orderFactory->createCalculatableOrderFromOrder($orderId, $newVersionContext);
        $newReturnOrdersAsNegatedOrders = array_values(array_map(
            fn(CalculatableOrder $returnOrderAsOrder) => $returnOrderAsOrder->negated(new PriceNegator()),
            $this->orderFactory->createCalculatableOrdersFromReturnOrdersOfOrder($orderId, $newVersionContext),
        ));

        // Difference = (NewOrder - NewReturnOrders) - (OldOrder - OldReturnOrders)
        return $this->orderCalculationService->mergeOrders(
            $newOrder,
            $oldOrder->negated(new PriceNegator()),
            ...$newReturnOrdersAsNegatedOrders,
            ...$oldReturnOrdersAsOrders,
        );
    }
}
