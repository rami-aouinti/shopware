<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class OrderDeliveryCollectionExtension
{
    /**
     * Returns the order delivery that is shown by the Shopware Administration.
     *
     * This is the order delivery with the highest shipping costs as Shopware creates additional order deliveries with
     * negative shipping costs when applying shipping costs vouchers. Just using the first or last order delivery
     * without sorting first can result in the wrong order delivery to be used.
     */
    public static function primaryOrderDelivery(OrderDeliveryCollection $collection): ?OrderDeliveryEntity
    {
        $collectionCopy = OrderDeliveryCollection::createFrom($collection);
        // Sort by shippingCosts ascending
        $collectionCopy->sort(
            fn(OrderDeliveryEntity $a, OrderDeliveryEntity $b) =>
                $a->getShippingCosts()->getTotalPrice() <=> $b->getShippingCosts()->getTotalPrice(),
        );

        return $collectionCopy->last();
    }
}
