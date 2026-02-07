<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Promotion;

use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Overwrites the CartScopeDiscountPackager to fix the (re)calculations of discounts for orders with product sets.
 *
 * (Re)calculation of discounts are based on "matching items" that are stored in the discount package. Product set order
 * line items positions would be filtered by Shopware and, in turn, would not be included in the discount. This
 * decorator adds the product set line items to the discount package to fix this.
 */
class CartScopeDiscountPackagerDecorator extends CartScopeDiscountPackager
{
    public function __construct(private readonly CartScopeDiscountPackager $decorated) {}

    public function getDecorated(): DiscountPackager
    {
        return $this->decorated;
    }

    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        $discountPackages = $this->decorated->getMatchingItems($discount, $cart, $context);

        // Add line items of type product set which are stackable (can have a quantity > 1) to the discount package.
        // This mimics the behavior of the shopware's CartScopeDiscountPackager.
        $productSetLineItems = $cart->getLineItems()->filter(fn(LineItem $lineItem) => $lineItem->getType() === ProductSetDefinition::LINE_ITEM_TYPE && $lineItem->isStackable());

        if ($productSetLineItems->count() === 0) {
            return $discountPackages;
        }

        $priceDefinition = $discount->getPriceDefinition();
        if ($priceDefinition instanceof FilterableInterface && $priceDefinition->getFilter()) {
            $productSetLineItems = $productSetLineItems->filter(fn(LineItem $lineItem) => $priceDefinition->getFilter()?->match(new LineItemScope($lineItem, $context)));
        }

        $this->addProductSetLineItemsToDiscountPackage($discountPackages, $productSetLineItems);

        return $discountPackages;
    }

    private function addProductSetLineItemsToDiscountPackage(DiscountPackageCollection $discountPackages, LineItemCollection $productSetLineItems): void
    {
        $productSetDiscountItems = [];
        foreach ($productSetLineItems as $productSetLineItem) {
            for ($i = 1; $i <= $productSetLineItem->getQuantity(); ++$i) {
                // Add each product set line item with a quantity of 1 to the discount package.
                // This is also done in the CartScopeDiscountPackager. It calculates the discount for each unit
                // of the line item separately.
                // (see https://github.com/shopware/shopware/blob/55b0191f662cdf517c84f8886319270c2def64a7/src/Core/Checkout/Promotion/Cart/PromotionCalculator.php#L405-L421)
                $item = new LineItemQuantity(
                    $productSetLineItem->getId(),
                    1,
                );

                $productSetDiscountItems[] = $item;
            }
        }

        if (count($productSetDiscountItems) === 0) {
            return;
        }

        $discountPackages->add(new DiscountPackage(new LineItemQuantityCollection($productSetDiscountItems)));
    }
}
