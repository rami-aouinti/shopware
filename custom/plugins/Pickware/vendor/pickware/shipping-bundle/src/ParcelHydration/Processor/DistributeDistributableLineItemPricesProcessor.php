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

use Pickware\MoneyBundle\Currency;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\ParcelHydration\OrderLineItemParcelMapping;
use Pickware\ShippingBundle\ParcelHydration\ParcelItemHydrator;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\State;

/**
 * This processor distributes the value of certain non-physical line items
 * proportionally across eligible parcel items. This way, each parcel item
 * gets an adjusted unit price reflecting its share of the overall order value.
 */
class DistributeDistributableLineItemPricesProcessor implements ParcelItemsProcessor
{
    private const ORDER_LINE_ITEMS_TYPES_TO_DISTRIBUTE = [
        LineItem::PROMOTION_LINE_ITEM_TYPE,
        LineItem::DISCOUNT_LINE_ITEM,
        LineItem::CUSTOM_LINE_ITEM_TYPE,
    ];

    /**
     * @param OrderLineItemParcelMapping[] $items
     * @return OrderLineItemParcelMapping[]
     */
    public function process(array $items, ProcessorContext $processorContext): array
    {
        $totalDistributionValue = array_sum(array_map(
            fn(OrderLineItemParcelMapping $item) => $this->isDistributableLineItem($item->getOrderLineItem()) ? $item->getOrderLineItem()->getPrice()?->getTotalPrice() : 0.0,
            $items,
        ));

        // We explicitly want digital products to be included for distribution even though they
        // are not contained in the parcel.
        $totalReceivingValue = array_sum(array_map(
            fn(OrderLineItemParcelMapping $item) => $this->isEligibleForDistribution($item->getOrderLineItem(), true) ? $item->getOrderLineItem()->getPrice()?->getTotalPrice() : 0.0,
            $items,
        ));

        foreach ($items as $item) {
            $orderLineItem = $item->getOrderLineItem();
            $parcelItem = $item->getParcelItem();

            if (!$parcelItem || !$this->isEligibleForDistribution($orderLineItem, false)) {
                continue;
            }

            $unitPrice = $orderLineItem->getPrice()?->getUnitPrice() ?? 0.0;

            if ($totalReceivingValue > 0) {
                $unitPriceAdjustment = ($orderLineItem->getPrice()?->getTotalPrice() / $totalReceivingValue) * $totalDistributionValue;
                $quantity = $orderLineItem->getPrice()?->getQuantity();
                if ($quantity > 0) {
                    $unitPrice += $unitPriceAdjustment / $quantity;
                }
            }

            $parcelItem->setUnitPrice(
                new MoneyValue($unitPrice, new Currency($processorContext->getOrderCurrency()->getIsoCode())),
            );
        }

        return $items;
    }

    private function isDistributableLineItem(OrderLineItemEntity $orderLineItem): bool
    {
        return in_array($orderLineItem->getType(), self::ORDER_LINE_ITEMS_TYPES_TO_DISTRIBUTE, true);
    }

    private function isEligibleForDistribution(
        OrderLineItemEntity $orderLineItem,
        bool $supportDigitalProducts,
    ): bool {
        if ($orderLineItem->getPrice()?->getQuantity() === 0) {
            return false;
        }

        if ($orderLineItem->getQuantity() <= 0) {
            return false;
        }

        if (!in_array($orderLineItem->getType(), [LineItem::PRODUCT_LINE_ITEM_TYPE, ParcelItemHydrator::PRODUCT_SET_TYPE], true)) {
            return false;
        }

        if (!$supportDigitalProducts && in_array(State::IS_DOWNLOAD, $orderLineItem->getStates(), true)) {
            return false;
        }

        return true;
    }
}
