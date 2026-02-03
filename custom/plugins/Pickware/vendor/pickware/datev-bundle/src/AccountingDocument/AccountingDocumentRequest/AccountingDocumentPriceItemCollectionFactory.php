<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrder;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrderLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Framework\Util\FloatComparator;

class AccountingDocumentPriceItemCollectionFactory
{
    public function createPriceItemCollection(CartPrice $orderPrice): AccountingDocumentPriceItemCollection
    {
        $priceItems = [];
        switch ($orderPrice->getTaxStatus()) {
            case CartPrice::TAX_STATE_FREE:
                $priceItems[] = new AccountingDocumentPriceItem(price: $orderPrice->getTotalPrice(), taxRate: null);
                break;

            case CartPrice::TAX_STATE_GROSS:
                /** @var CalculatedTax $calculatedTax */
                foreach ($orderPrice->getCalculatedTaxes() as $calculatedTax) {
                    $priceItems[] = new AccountingDocumentPriceItem(
                        price: $calculatedTax->getPrice(),
                        taxRate: $calculatedTax->getTaxRate(),
                    );
                }
                break;

            case CartPrice::TAX_STATE_NET:
                /** @var CalculatedTax $calculatedTax */
                foreach ($orderPrice->getCalculatedTaxes() as $calculatedTax) {
                    $priceItems[] = new AccountingDocumentPriceItem(
                        price: $calculatedTax->getPrice() + $calculatedTax->getTax(),
                        taxRate: $calculatedTax->getTaxRate(),
                    );
                }
                break;
        }

        return AccountingDocumentPriceItemCollection::create($priceItems)->filterNonContributingPriceItems();
    }

    public function createPriceItemCollectionForBrokenShopifyOrder(CartPrice $orderPrice): AccountingDocumentPriceItemCollection
    {
        $priceItems = [];
        $hasZeroTaxRateItem = false;
        switch ($orderPrice->getTaxStatus()) {
            case CartPrice::TAX_STATE_FREE:
                $priceItems[] = new AccountingDocumentPriceItem(price: $orderPrice->getTotalPrice(), taxRate: null);
                break;

            case CartPrice::TAX_STATE_GROSS:
            case CartPrice::TAX_STATE_NET:
                /** @var CalculatedTax $calculatedTax */
                foreach ($orderPrice->getCalculatedTaxes() as $calculatedTax) {
                    if ($calculatedTax->getTaxRate() === 0.0) {
                        $hasZeroTaxRateItem = true;

                        continue;
                    }

                    $priceItems[] = new AccountingDocumentPriceItem(
                        price: round(($calculatedTax->getTax() / ($calculatedTax->getTaxRate() / 100)) + $calculatedTax->getTax(), 2),
                        taxRate: $calculatedTax->getTaxRate(),
                    );
                }
                break;
        }

        if ($orderPrice->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            return AccountingDocumentPriceItemCollection::create($priceItems)->filterNonContributingPriceItems();
        }

        // Calculate the full cents to distribute over all price items (can be negative). This is non-zero exactly when
        // rounding errors from recalculation lead to wrong total prices.
        $totalCalculatedOrderPriceInCents = round(
            100 * array_reduce(
                $priceItems,
                fn(float $carry, AccountingDocumentPriceItem $priceItem) => $carry + $priceItem->getPrice(),
                0.0,
            ),
        );
        $roundedOrderPriceInCents = round($orderPrice->getTotalPrice() * 100);
        $remainingOrderPriceInCents = $roundedOrderPriceInCents - $totalCalculatedOrderPriceInCents;
        if ($hasZeroTaxRateItem) {
            // add in the AccountingDocumentPriceItem for the 0% tax rate if there was one and assign it all the remaining cents
            $priceItems[] = new AccountingDocumentPriceItem(
                price: round(($remainingOrderPriceInCents / 100), 2),
                taxRate: 0.0,
            );

            return AccountingDocumentPriceItemCollection::create($priceItems)->filterNonContributingPriceItems();
        }

        $sign = $remainingOrderPriceInCents < 0 ? -1 : 1;
        $fullCentsPerPriceItem = 0;
        if ($remainingOrderPriceInCents !== 0.0) {
            $fullCentsPerPriceItem = (int) floor(abs($remainingOrderPriceInCents) / count($priceItems));
            $priceItems = array_map(
                fn(AccountingDocumentPriceItem $priceItem) => new AccountingDocumentPriceItem(
                    price: round($priceItem->getPrice() + ($sign * ($fullCentsPerPriceItem / 100)), 2),
                    taxRate: $priceItem->getTaxRate(),
                ),
                $priceItems,
            );
        }

        $missingCents = (int) round(abs($remainingOrderPriceInCents) - $fullCentsPerPriceItem * count($priceItems));
        if ($missingCents > 0) {
            foreach (range(0, $missingCents - 1) as $indexToFillMissingCent) {
                $priceItems[$indexToFillMissingCent] = new AccountingDocumentPriceItem(
                    price: round($priceItems[$indexToFillMissingCent]->getPrice() + $sign * 0.01, 2),
                    taxRate: $priceItems[$indexToFillMissingCent]->getTaxRate(),
                );
            }
        }

        return AccountingDocumentPriceItemCollection::create($priceItems)->filterNonContributingPriceItems();
    }

    /**
     * Should not be called, only applied for orders that were made between shopify breaking their tax calculation and
     * us fixing it. Since we don't want to invest more time into practically dead code this is only integration tested
     */
    public function createPriceItemCollectionForBrokenShopifyCalculatedTaxesUsingTwoSteps(CalculatableOrder $calculatableOrder): AccountingDocumentPriceItemCollection
    {
        $orderPrice = $calculatableOrder->price;
        $positionPrices = new ImmutableCollection([
            $calculatableOrder->shippingCosts,
            ...array_values(array_map(
                fn(CalculatableOrderLineItem $lineItem) => $lineItem->price,
                $calculatableOrder->lineItems,
            )),
        ]);
        $positionPricesTaxRateCount = $positionPrices->reduce(
            initialValue: 0,
            callback: fn(int $carry, CalculatedPrice $price) => $carry + $price->getCalculatedTaxes()->count(),
        );
        // Since rounding issues can arise per order position per tax rate and have a maximal deviation of 0.5 cents
        // from the actual value, we allow such a deviation for each actual line item tax rate before we interpret a tax
        // rate on an order price as "broken" / "needing correction through backward calculation".
        $maximalAllowedForwardTaxDeviation = $positionPricesTaxRateCount * 0.005;

        // Step 1: Calculate per-calculated-tax deviations from their gross taxed price and add the total deviation to a
        // zero tax rate item. This deviation is calculated _per tax rate_ line and summed up.
        $accumulatedDeviation = 0.0;
        $deviationAwarePriceItemFactory = function(
            CalculatedTax $calculatedTax,
            float $forwardTax,
            float $grossTaxedPrice,
        ) use (
            &$accumulatedDeviation,
            $maximalAllowedForwardTaxDeviation
        ): AccountingDocumentPriceItem {
            if (FloatComparator::lessThanOrEquals(abs($calculatedTax->getTax() - $forwardTax), $maximalAllowedForwardTaxDeviation)) {
                return new AccountingDocumentPriceItem($grossTaxedPrice, $calculatedTax->getTaxRate());
            }

            $backwardGrossTaxedPrice = round(($calculatedTax->getTax() / ($calculatedTax->getTaxRate() / 100)) + $calculatedTax->getTax(), 2);
            $accumulatedDeviation += $grossTaxedPrice - $backwardGrossTaxedPrice;

            return new AccountingDocumentPriceItem($backwardGrossTaxedPrice, $calculatedTax->getTaxRate());
        };

        $transformedNonZeroTaxRatePriceItems = [];
        $zeroTaxRatePriceItem = null;
        switch ($orderPrice->getTaxStatus()) {
            case CartPrice::TAX_STATE_FREE:
                return AccountingDocumentPriceItemCollection::create([
                    new AccountingDocumentPriceItem(price: $orderPrice->getTotalPrice(), taxRate: null),
                ])->filterNonContributingPriceItems();

            case CartPrice::TAX_STATE_GROSS:
                /** @var CalculatedTax $calculatedTax */
                foreach ($orderPrice->getCalculatedTaxes() as $calculatedTax) {
                    if ($calculatedTax->getTaxRate() === 0.0) {
                        $zeroTaxRatePriceItem = new AccountingDocumentPriceItem($calculatedTax->getPrice(), taxRate: 0.0);
                    } else {
                        $transformedNonZeroTaxRatePriceItems[] = $deviationAwarePriceItemFactory(
                            calculatedTax: $calculatedTax,
                            forwardTax: round(
                                num: $calculatedTax->getPrice() - round(
                                    num: $calculatedTax->getPrice() / (1 + $calculatedTax->getTaxRate() / 100),
                                    precision: 2,
                                ),
                                precision: 2,
                            ),
                            grossTaxedPrice: $calculatedTax->getPrice(),
                        );
                    }
                }
                break;

            case CartPrice::TAX_STATE_NET:
                /** @var CalculatedTax $calculatedTax */
                foreach ($orderPrice->getCalculatedTaxes() as $calculatedTax) {
                    if ($calculatedTax->getTaxRate() === 0.0) {
                        $zeroTaxRatePriceItem = new AccountingDocumentPriceItem($calculatedTax->getPrice() + $calculatedTax->getTax(), taxRate: 0.0);
                    } else {
                        $transformedNonZeroTaxRatePriceItems[] = $deviationAwarePriceItemFactory(
                            calculatedTax: $calculatedTax,
                            forwardTax: round($calculatedTax->getPrice() * $calculatedTax->getTaxRate() / 100, 2),
                            grossTaxedPrice: $calculatedTax->getPrice() + $calculatedTax->getTax(),
                        );
                    }
                }
                break;
        }

        $newZeroTaxRatePriceItem = new AccountingDocumentPriceItem(
            price: ($zeroTaxRatePriceItem?->getPrice() ?? 0.0) + $accumulatedDeviation,
            taxRate: 0.0,
        );

        // Step 2: Calculate the remaining total deviation from the order total (all tax rates combined) and add this
        // deviation to the zero tax rate item, too.
        /** @var float $totalPriceFromAllPriceItems */
        $totalPriceFromAllPriceItems = AccountingDocumentPriceItemCollection::create([
            ...$transformedNonZeroTaxRatePriceItems,
            $newZeroTaxRatePriceItem,
        ])->reduce(
            0.0,
            fn(float $carry, AccountingDocumentPriceItem $item) => $item->getPrice() + $carry,
        );

        $deviationFromOrderPrice = $orderPrice->getTotalPrice() - $totalPriceFromAllPriceItems;

        return AccountingDocumentPriceItemCollection::create([
            new AccountingDocumentPriceItem(
                price: round(num: $newZeroTaxRatePriceItem->getPrice() + $deviationFromOrderPrice, precision: 2),
                taxRate: 0.0,
            ),
            ...$transformedNonZeroTaxRatePriceItems,
        ])->filterNonContributingPriceItems();
    }
}
