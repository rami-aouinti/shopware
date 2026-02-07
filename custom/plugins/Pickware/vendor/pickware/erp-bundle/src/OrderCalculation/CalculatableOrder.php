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

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Util\FloatComparator;

/**
 * A helper class that is a sub set of Shopware's OrderEntity that has the relevant price and line item information
 * needed for invoice correction calculation.
 */
class CalculatableOrder
{
    /**
     * @var CalculatableOrderLineItem[]
     */
    public array $lineItems;

    public CartPrice $price;
    public CalculatedPrice $shippingCosts;

    public static function createFrom(CalculatableOrder $order): self
    {
        $self = new CalculatableOrder();
        $self->lineItems = array_values(array_map(
            fn(CalculatableOrderLineItem $orderLineItem) => CalculatableOrderLineItem::createFrom($orderLineItem),
            $order->lineItems,
        ));
        $self->price = $order->price;
        $self->shippingCosts = $order->shippingCosts;

        return $self;
    }

    public function negated(PriceNegator $priceNegator): self
    {
        $negatedOrder = new CalculatableOrder();
        $negatedOrder->lineItems = array_map(
            fn(CalculatableOrderLineItem $orderLineItem) => $orderLineItem->negated($priceNegator),
            $this->lineItems,
        );
        $negatedOrder->price = $priceNegator->negateCartPrice($this->price);
        $negatedOrder->shippingCosts = $priceNegator->negateShippingCosts($this->shippingCosts);

        return $negatedOrder;
    }

    public function addLineItem(CalculatableOrderLineItem $lineItem): void
    {
        $this->lineItems[] = $lineItem;
    }

    /**
     * A calculatable order is considered "empty" when there are no line items, and no price or shipping costs (<> 0)
     * are set.
     */
    public function isEmpty()
    {
        if (count($this->lineItems) > 0) {
            return false;
        }
        if (
            !FloatComparator::equals(0, $this->price->getRawTotal())
            || !FloatComparator::equals(0, $this->price->getPositionPrice())
            || !FloatComparator::equals(0, $this->price->getNetPrice())
            || !FloatComparator::equals(0, $this->price->getTotalPrice())
        ) {
            return false;
        }
        if (
            !FloatComparator::equals(0, $this->shippingCosts->getTotalPrice())
            || !FloatComparator::equals(0, $this->shippingCosts->getUnitPrice())
        ) {
            return false;
        }

        return true;
    }
}
