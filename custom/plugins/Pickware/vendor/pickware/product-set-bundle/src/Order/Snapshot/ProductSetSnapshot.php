<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Order\Snapshot;

use Pickware\PhpStandardLibrary\Collection\CountingMap;

/**
 * This is a snapshot of a product set configuration: A product set order line item and its assigned child order line items at the time of the order checkout.
 * Note that this is the configuration represensation (the assigned child order line items per single product set) not the actual quantities of the order.
 */
class ProductSetSnapshot
{
    /**
     * @param CountingMap<string> $childOrderLineItemQuantities
     */
    public function __construct(
        private readonly string $parentOrderLineItemId,
        private readonly CountingMap $childOrderLineItemQuantities,
    ) {}

    public function getParentOrderLineItemId(): string
    {
        return $this->parentOrderLineItemId;
    }

    /**
     * @return CountingMap<string>
     */
    public function getChildOrderLineItemQuantities(): CountingMap
    {
        return $this->childOrderLineItemQuantities;
    }

    /**
     * @param CountingMap<string> $orderLineItemQuantities
     * @return CountingMap<string>
     */
    public function replaceChildLineItemsWithParentLineItem(CountingMap $orderLineItemQuantities): CountingMap
    {
        if ($orderLineItemQuantities->isEmpty() || $this->childOrderLineItemQuantities->isEmpty()) {
            return $orderLineItemQuantities;
        }

        while ($this->childOrderLineItemQuantities->isSubsetOf($orderLineItemQuantities)) {
            $orderLineItemQuantities->subtractMap($this->childOrderLineItemQuantities);
            $orderLineItemQuantities->add($this->parentOrderLineItemId, 1);
        }

        return $orderLineItemQuantities;
    }
}
