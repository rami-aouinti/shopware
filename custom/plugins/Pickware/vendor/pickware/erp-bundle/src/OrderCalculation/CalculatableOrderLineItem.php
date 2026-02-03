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

/**
 * A helper class that is a sub set of Shopware's OrderLineItemEntity that has the relevant price information needed for
 * invoice correction calculation.
 */
class CalculatableOrderLineItem
{
    public ?string $productId = null;
    public ?string $productVersionId = null;
    public array $payload = [];
    public string $label;
    public ?CalculatedPrice $price;
    public int $quantity;
    public ?string $type;
    public ?int $position = null;

    /**
     * The id of the order line item that is the single origin for this line item. Is null when the line item
     * was combined from two different order line items, or when the originating line item did not have an order
     * line item ID (e.g., a custom return line item).
     */
    public ?string $singleOriginatingOrderLineItemId = null;

    public static function createFrom(CalculatableOrderLineItem $orderLineItem): self
    {
        $self = new CalculatableOrderLineItem();
        $self->type = $orderLineItem->type;
        $self->label = $orderLineItem->label;
        $self->price = $orderLineItem->price;
        $self->quantity = $orderLineItem->quantity;
        $self->productId = $orderLineItem->productId;
        $self->productVersionId = $orderLineItem->productVersionId;
        $self->payload = $orderLineItem->payload;
        $self->position = $orderLineItem->position;
        $self->singleOriginatingOrderLineItemId = $orderLineItem->singleOriginatingOrderLineItemId;

        return $self;
    }

    /**
     * Negates and returns this order line item by negating the item's quantity and therefore the total price (but not
     * the unit price!). This also changes the calculated prices and tax values.
     */
    public function negated(PriceNegator $priceNegator): self
    {
        $negatedOrderLineItem = new CalculatableOrderLineItem();
        $negatedOrderLineItem->productId = $this->productId;
        $negatedOrderLineItem->productVersionId = $this->productVersionId;
        $negatedOrderLineItem->label = $this->label;
        $negatedOrderLineItem->type = $this->type;
        $negatedOrderLineItem->quantity = -1 * $this->quantity;
        $negatedOrderLineItem->price = $priceNegator->negateCalculatedPrice($this->price);
        $negatedOrderLineItem->payload = $this->payload;
        $negatedOrderLineItem->position = $this->position;
        $negatedOrderLineItem->singleOriginatingOrderLineItemId = $this->singleOriginatingOrderLineItemId;

        return $negatedOrderLineItem;
    }
}
