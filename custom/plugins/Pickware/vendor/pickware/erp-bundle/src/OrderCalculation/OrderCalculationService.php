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

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Util\FloatComparator;

class OrderCalculationService
{
    public function __construct(
        private readonly PriceTotalCalculator $priceTotalCalculator,
    ) {}

    /**
     * Merges the given orders into a single order regarding prices and line items. Same order line items of the summed
     * up order will be reduced my merging (see reduceOrderLineItems()). Line item positions are modified to keep the
     * sorting within the given orders as much as possible.
     */
    public function mergeOrders(CalculatableOrder $baseOrder, CalculatableOrder ...$orders): CalculatableOrder
    {
        $mergedOrder = CalculatableOrder::createFrom($baseOrder);

        $lineItemPositionOffset = count($baseOrder->lineItems);
        foreach ($orders as $order) {
            foreach ($order->lineItems as $lineItem) {
                $lineItem->position += $lineItemPositionOffset;
                $mergedOrder->addLineItem($lineItem);
            }
            $lineItemPositionOffset += count($order->lineItems);

            $mergedOrder->price = $this->priceTotalCalculator->sumCartPrices($mergedOrder->price, $order->price);
            $mergedOrder->shippingCosts = $this->priceTotalCalculator->sumShippingCosts(
                $mergedOrder->shippingCosts,
                $order->shippingCosts,
            );
        }

        $mergedOrder->lineItems = $this->consolidateOrderLineItems($mergedOrder->lineItems);

        return $mergedOrder;
    }

    /**
     * Consolidates the order line items of the given order by merging 'matching' order line items together to a single
     * order line item: add quantities, add prices, add taxes.
     *
     * Since there may be negative order line item quantities, the resulting merged order line item may have 0 quantity.
     * The resulting order line item will be removed from the order in this case.
     *
     * @param CalculatableOrderLineItem[] $orderLineItems
     * @return CalculatableOrderLineItem[]
     */
    private function consolidateOrderLineItems(array $orderLineItems): array
    {
        foreach ($orderLineItems as $keyA => $orderLineItemA) {
            foreach ($orderLineItems as $keyB => $orderLineItemB) {
                if ($keyA === $keyB) {
                    continue;
                }

                $combinedLineItem = null;
                if ($this->checkOrderLineItemsMatch($orderLineItemA, $orderLineItemB)) {
                    $combinedLineItem = $this->consolidateLineItems($orderLineItemA, $orderLineItemB);
                } elseif ($this->checkDiscountOrderLineItemsMatch($orderLineItemA, $orderLineItemB)) {
                    $combinedLineItem = $this->consolidateDiscountLineItems($orderLineItemA, $orderLineItemB);
                }

                if ($combinedLineItem) {
                    // If the quantity was reduced to zero, the order line item is removed from the result list.
                    if ($combinedLineItem->price->getQuantity() === 0 && $combinedLineItem->quantity === 0) {
                        unset($orderLineItems[$keyA]);
                    } else {
                        $orderLineItems[$keyA] = $combinedLineItem;
                    }
                    unset($orderLineItems[$keyB]);

                    // If an order line item was reduced/removed while we are looping through the lists, break the loop
                    // and run again.
                    return $this->consolidateOrderLineItems($orderLineItems);
                }
            }
        }

        return $orderLineItems;
    }

    /**
     * Compares two given order line items and determines whether they reference the same real-world order line item.
     *
     * Order line items are considered matching if:
     *  - they reference the same product and in turn the same type and label
     *  - they have the same absolute unit price
     *  - they have the same tax rules, excluding proportions (e.g. "90% taxed at 19.00%, 10% taxed at 7.00%" is
     *    considered the same as "75% taxed at 19.00%, 25% taxed at 7.00%")
     *
     * Order line items are still considered matching if they have different quantities and therefore different totals
     * and total tax values.
     */
    private function checkOrderLineItemsMatch(CalculatableOrderLineItem $orderLineItemA, CalculatableOrderLineItem $orderLineItemB): bool
    {
        if ($orderLineItemA->type !== $orderLineItemB->type) {
            return false;
        }
        if ($orderLineItemA->productId !== $orderLineItemB->productId) {
            return false;
        }
        if ($orderLineItemA->productId !== null && $orderLineItemA->productVersionId !== $orderLineItemB->productVersionId) {
            return false;
        }
        if ($orderLineItemA->label !== $orderLineItemB->label) {
            return false;
        }

        $price1 = $orderLineItemA->price;
        $price2 = $orderLineItemB->price;
        if (!FloatComparator::equals(abs($price1->getUnitPrice()), abs($price2->getUnitPrice()))) {
            return false;
        }

        return $this->checkTaxRulesMatch($orderLineItemA, $orderLineItemB);
    }

    /**
     * Consolidates two line items by summing up quantities and prices.
     */
    private function consolidateLineItems(CalculatableOrderLineItem $orderLineItemA, CalculatableOrderLineItem $orderLineItemB): CalculatableOrderLineItem
    {
        // Check whether one order line item is negated and flip it before summing if necessary.
        if (
            $orderLineItemA->price->getUnitPrice() !== 0.0
            && FloatComparator::equals($orderLineItemA->price->getUnitPrice(), -1 * $orderLineItemB->price->getUnitPrice())
        ) {
            $orderLineItemB->price = new CalculatedPrice(
                $orderLineItemB->price->getUnitPrice() * -1,
                $orderLineItemB->price->getTotalPrice(),
                $orderLineItemB->price->getCalculatedTaxes(),
                $orderLineItemB->price->getTaxRules(),
                $orderLineItemB->price->getQuantity() * -1,
                $orderLineItemB->price->getReferencePrice(),
                $orderLineItemB->price->getListPrice(),
            );
            $orderLineItemB->quantity *= -1;
        }

        $orderLineItemA->price = $this->priceTotalCalculator->sumCalculatedPrices(
            $orderLineItemA->price,
            $orderLineItemB->price,
        );
        $orderLineItemA->quantity = $orderLineItemA->quantity + $orderLineItemB->quantity;
        $orderLineItemA->singleOriginatingOrderLineItemId = null;

        return $orderLineItemA;
    }

    private function checkDiscountOrderLineItemsMatch(CalculatableOrderLineItem $orderLineItemA, CalculatableOrderLineItem $orderLineItemB): bool
    {
        if ($orderLineItemA->type !== LineItem::DISCOUNT_LINE_ITEM || $orderLineItemB->type !== LineItem::DISCOUNT_LINE_ITEM) {
            return false;
        }
        if ($orderLineItemA->label !== $orderLineItemB->label) {
            return false;
        }
        if ($orderLineItemA->singleOriginatingOrderLineItemId !== $orderLineItemB->singleOriginatingOrderLineItemId) {
            return false;
        }
        if (abs($orderLineItemA->quantity) !== 1 || abs($orderLineItemB->quantity) !== 1) {
            return false;
        }

        return $this->checkTaxRulesMatch($orderLineItemA, $orderLineItemB);
    }

    /**
     * Consolidates two discount line items by calculating the price difference.
     */
    private function consolidateDiscountLineItems(CalculatableOrderLineItem $orderLineItemA, CalculatableOrderLineItem $orderLineItemB): CalculatableOrderLineItem
    {
        $combinedDiscountPrice = $orderLineItemA->price->getUnitPrice() * $orderLineItemA->quantity + $orderLineItemB->price->getUnitPrice() * $orderLineItemB->quantity;
        // When both order line items had the same quantity, keep it. Otherwise, set the combined quantity to 1.
        $combinedQuantity = $orderLineItemA->quantity === $orderLineItemB->quantity ? $orderLineItemA->quantity : 1;

        // Ensure the unit price for a discount remains negative for the combined line item.
        if ($combinedDiscountPrice > 0.0) {
            $combinedDiscountPrice *= -1;
            $combinedQuantity *= -1;
        }

        $orderLineItemA->price = new CalculatedPrice(
            $combinedDiscountPrice,
            $combinedDiscountPrice * $combinedQuantity,
            $this->priceTotalCalculator->sumCalculatedTaxCollections(
                $orderLineItemA->price->getCalculatedTaxes(),
                $orderLineItemB->price->getCalculatedTaxes(),
            ),
            $orderLineItemA->price->getTaxRules(),
            $combinedQuantity,
            $orderLineItemA->price->getReferencePrice(),
            $orderLineItemA->price->getListPrice(),
        );
        $orderLineItemA->quantity = $combinedQuantity;

        return $orderLineItemA;
    }

    private function checkTaxRulesMatch(CalculatableOrderLineItem $orderLineItemA, CalculatableOrderLineItem $orderLineItemB): bool
    {
        $taxRules1 = $orderLineItemA->price->getTaxRules();
        $taxRules2 = $orderLineItemB->price->getTaxRules();
        if ($taxRules1->count() !== $taxRules2->count()) {
            return false;
        }
        // Since tax rule collections are mapped by the tax rate of the tax rule, we can use this key for the comparison
        foreach ($taxRules1->getKeys() as $taxRate) {
            $taxRule1 = $taxRules1->get($taxRate);
            $taxRule2 = $taxRules2->get($taxRate);
            if (!$taxRule2) {
                // A tax rule in tax rules collection 1 was not found (by tax rate) in tax rules collection 2
                return false;
            }
            if (!FloatComparator::equals($taxRule1->getTaxRate(), $taxRule2->getTaxRate())) {
                return false;
            }
            // We do _not_ compare tax rules proportions (`$taxRule1->getPercentage()`). Because the tax rules may
            // change for "the same" line item: If their price calculation is "auto", the tax rules proportion (e.g.
            // "75% taxed at 19%, 25% taxed at 7%) may change. This is easily true for %s discounts on the whole cart.
            // The tax rules proportion is also not displayed on the invoice document.
        }

        return true;
    }
}
