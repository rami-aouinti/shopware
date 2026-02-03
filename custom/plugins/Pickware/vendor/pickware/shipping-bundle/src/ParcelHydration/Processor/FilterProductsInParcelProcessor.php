<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration\Processor;

use Pickware\ShippingBundle\ParcelHydration\OrderLineItemParcelMapping;

class FilterProductsInParcelProcessor implements ParcelItemsProcessor
{
    /**
     * @param OrderLineItemParcelMapping[] $items
     * @return OrderLineItemParcelMapping[]
     */
    public function process(array $items, ProcessorContext $processorContext): array
    {
        $filterProducts = $processorContext->getConfig()->getFilterProducts();

        if ($filterProducts === null) {
            return $items;
        }

        $quantityByProductId = [];
        foreach ($filterProducts as $p) {
            $productId = $p['productId'];
            $quantityByProductId[$productId] = ($quantityByProductId[$productId] ?? 0) + $p['quantity'];
        }

        $result = [];
        foreach ($items as $item) {
            $productId = $item->getOrderLineItem()->getProductId();
            if ($productId === null) {
                continue;
            }
            $quantityInParcel = $quantityByProductId[$productId] ?? 0;
            if ($quantityInParcel <= 0) {
                continue;
            }
            $lineItemQuantity = min($quantityInParcel, $item->getOrderLineItem()->getQuantity());
            $item->getParcelItem()?->setQuantity($lineItemQuantity);
            $quantityByProductId[$productId] -= $lineItemQuantity;

            $result[] = $item;
        }

        return $result;
    }
}
