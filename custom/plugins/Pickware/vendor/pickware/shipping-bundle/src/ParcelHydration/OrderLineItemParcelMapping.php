<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration;

use Pickware\ShippingBundle\Parcel\ParcelItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemParcelMapping
{
    public function __construct(
        public readonly OrderLineItemEntity $orderLineItem,
        /**
         * Not every order line item maps to a parcel item (e.g. discounts),
         * so parcelItem may be null initially or remain null throughout processing.
         */
        public ?ParcelItem $parcelItem,
    ) {}

    public function getOrderLineItem(): OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    public function getParcelItem(): ?ParcelItem
    {
        return $this->parcelItem;
    }

    public function setParcelItem(?ParcelItem $parcelItem): void
    {
        $this->parcelItem = $parcelItem;
    }
}
