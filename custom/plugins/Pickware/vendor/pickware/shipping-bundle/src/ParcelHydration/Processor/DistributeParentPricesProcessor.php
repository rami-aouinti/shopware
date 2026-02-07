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

use LogicException;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\ParcelHydration\OrderLineItemParcelMapping;

/**
 * This processor distributes the unit price of parent line items (e.g. product sets)
 * proportionally to their child line items. The distribution is based on the product price
 * of each child to reflect a realistic value allocation across the set.
 */
class DistributeParentPricesProcessor implements ParcelItemsProcessor
{
    /**
     * @param OrderLineItemParcelMapping[] $items
     * @return OrderLineItemParcelMapping[]
     */
    public function process(array $items, ProcessorContext $processorContext): array
    {
        $childrenByParentId = $this->groupChildrenByParentId($items);

        foreach ($childrenByParentId as $parentId => $children) {
            $parent = $this->findItemByOrderLineItemId($items, $parentId);
            $this->distributeParentPrice($parent, $children);
        }

        return $items;
    }

    /**
     * @param OrderLineItemParcelMapping[] $children
     * Distributes parent's price among children proportionally to their product prices,
     * using the largest remainder method to avoid rounding errors.
     */
    private function distributeParentPrice(
        OrderLineItemParcelMapping $parent,
        array $children,
    ): void {
        if (empty($children)) {
            return;
        }
        $parentPrice = $parent->getParcelItem()?->getUnitPrice();
        if ($parentPrice === null || $parentPrice->getValue() < 0.001) {
            return;
        }

        if (!$this->areAllLineItemPricesNullOrZero($children)) {
            return;
        }

        if ($this->isAnyLineItemQuantityZero($children)) {
            return;
        }

        // The total children product price may differ from the total price of the parent line item (i.e. in product sets).
        $totalChildrenValue = MoneyValue::sum(
            ...array_map(
                fn(OrderLineItemParcelMapping $child): MoneyValue => $child->getParcelItem()->getTotalPrice() ?? MoneyValue::zero(),
                $children,
            ),
        );

        if ($totalChildrenValue->getValue() <= 0.001) {
            return;
        }

        foreach ($children as $child) {
            $childOrderLineItem = $child->getOrderLineItem();
            $childTotalPrice = $child->getParcelItem()->getTotalPrice() ?? MoneyValue::zero();
            $childTotalShare = MoneyValue::ratio($childTotalPrice, $totalChildrenValue);

            $quantity = $childOrderLineItem->getQuantity();

            $unitPrice = $parentPrice->multiply($childTotalShare / $quantity);

            $child->getParcelItem()->setUnitPrice($unitPrice);
        }
    }

    /**
     * @param OrderLineItemParcelMapping[] $items
     * @return array<string, OrderLineItemParcelMapping[]>
     */
    private function groupChildrenByParentId(array $items): array
    {
        $childrenByParentId = [];

        foreach ($items as $item) {
            $parentId = $item->getOrderLineItem()->getParentId();
            if ($parentId !== null) {
                $childrenByParentId[$parentId][] = $item;
            }
        }

        return $childrenByParentId;
    }

    /**
     * @param OrderLineItemParcelMapping[] $items
     */
    private function findItemByOrderLineItemId(array $items, string $orderLineItemId): OrderLineItemParcelMapping
    {
        foreach ($items as $item) {
            if ($item->getOrderLineItem()->getId() === $orderLineItemId) {
                return $item;
            }
        }

        throw new LogicException('Missing entry which should be guaranteed.');
    }

    private function areAllLineItemPricesNullOrZero(array $children): bool
    {
        return array_reduce(
            $children,
            fn(bool $carry, OrderLineItemParcelMapping $child) => $carry && ($child->getOrderLineItem()->getPrice()->getTotalPrice() ?? 0.0) === 0.0,
            true,
        );
    }

    private function isAnyLineItemQuantityZero(array $children): bool
    {
        return array_reduce(
            $children,
            fn(bool $carry, OrderLineItemParcelMapping $child) => $carry || $child->getOrderLineItem()->getQuantity() === 0,
            false,
        );
    }
}
