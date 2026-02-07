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
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\State;

class FilterSupportedParcelItemsProcessor implements ParcelItemsProcessor
{
    /**
     * @param OrderLineItemParcelMapping[] $items
     * @return OrderLineItemParcelMapping[]
     */
    public function process(array $items, ProcessorContext $processorContext): array
    {
        return array_filter(
            $items,
            fn(OrderLineItemParcelMapping $item) => $this->isSupported($item->getOrderLineItem()),
        );
    }

    private function isSupported(OrderLineItemEntity $orderLineItem): bool
    {
        return
            $orderLineItem->getQuantity() > 0
            && $orderLineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE
            && !in_array(State::IS_DOWNLOAD, $orderLineItem->getStates(), true);
    }
}
