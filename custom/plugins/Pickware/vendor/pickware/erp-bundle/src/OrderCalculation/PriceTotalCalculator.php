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

use InvalidArgumentException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Framework\Util\FloatComparator;

class PriceTotalCalculator
{
    public function sumCartPrices(CartPrice $baseCartPrice, CartPrice ...$cartPrices): CartPrice
    {
        /** @var CartPrice $summedUpCartPrice */
        $summedUpCartPrice = CartPrice::createFrom($baseCartPrice);

        foreach ($cartPrices as $cartPrice) {
            if ($summedUpCartPrice->getTaxStatus() !== $cartPrice->getTaxStatus()) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot sum up cart prices with different tax status. Found status: %s, %s',
                    $summedUpCartPrice->getTaxStatus(),
                    $cartPrice->getTaxStatus(),
                ));
            }

            $summedUpCartPrice = new CartPrice(
                $summedUpCartPrice->getNetPrice() + $cartPrice->getNetPrice(),
                $summedUpCartPrice->getTotalPrice() + $cartPrice->getTotalPrice(),
                $summedUpCartPrice->getPositionPrice() + $cartPrice->getPositionPrice(),
                $this->sumCalculatedTaxCollections(
                    $summedUpCartPrice->getCalculatedTaxes(),
                    $cartPrice->getCalculatedTaxes(),
                ),
                $summedUpCartPrice->getTaxRules(), // We ignore and do not recalculate the tax rules for now.
                $summedUpCartPrice->getTaxStatus(),
                $summedUpCartPrice->getRawTotal() + $cartPrice->getRawTotal(),
            );
        }

        return $summedUpCartPrice;
    }

    /**
     * Sums up and returns the given CalculatedPrices. The UnitPrice of the given CalculatedPrices must be identical.
     */
    public function sumCalculatedPrices(
        CalculatedPrice $baseCalculatedPrice,
        CalculatedPrice ...$calculatedPrices,
    ): CalculatedPrice {
        /** @var CalculatedPrice $summedUpCalculatedPrice */
        $summedUpCalculatedPrice = CalculatedPrice::createFrom($baseCalculatedPrice);

        foreach ($calculatedPrices as $calculatedPrice) {
            if (!FloatComparator::equals($summedUpCalculatedPrice->getUnitPrice(), $calculatedPrice->getUnitPrice())) {
                throw new InvalidArgumentException(sprintf(
                    'Summing up calculated prices only works for calculated prices with the same unit price. Found '
                    . 'unit prices: %s and %s.',
                    $summedUpCalculatedPrice->getUnitPrice(),
                    $calculatedPrice->getUnitPrice(),
                ));
            }

            $summedUpCalculatedPrice = new CalculatedPrice(
                $summedUpCalculatedPrice->getUnitPrice(),
                $summedUpCalculatedPrice->getTotalPrice() + $calculatedPrice->getTotalPrice(),
                $this->sumCalculatedTaxCollections(
                    $summedUpCalculatedPrice->getCalculatedTaxes(),
                    $calculatedPrice->getCalculatedTaxes(),
                ),
                $summedUpCalculatedPrice->getTaxRules(), // We ignore and do not recalculate the tax rules for now.
                $summedUpCalculatedPrice->getQuantity() + $calculatedPrice->getQuantity(),
                $summedUpCalculatedPrice->getReferencePrice(),
                $summedUpCalculatedPrice->getListPrice(),
            );
        }

        return $summedUpCalculatedPrice;
    }

    /**
     * Sums up and returns the given ShippingCosts CalculatedPrices. Note that shipping costs will always have quantity
     * 1 and the values and totals be summed up arithmetically. This handling is different to sumCalculatedPrices().
     */
    public function sumShippingCosts(
        CalculatedPrice $baseShippingCosts,
        CalculatedPrice ...$shippingCosts,
    ): CalculatedPrice {
        /** @var CalculatedPrice $summedUpShippingCosts */
        $summedUpShippingCosts = CalculatedPrice::createFrom($baseShippingCosts);

        foreach ([$baseShippingCosts, ...$shippingCosts] as $shippingCost) {
            if ($shippingCost->getQuantity() !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'Summing up shipping costs only works for calculated prices with quantity 1. Found quantity: %s.',
                    $shippingCost->getQuantity(),
                ));
            }
        }

        foreach ($shippingCosts as $shippingCost) {
            $calculatedTaxes = $this->sumCalculatedTaxCollections(
                $summedUpShippingCosts->getCalculatedTaxes(),
                $shippingCost->getCalculatedTaxes(),
            );
            if ($calculatedTaxes->count() === 0) {
                // Since shipping costs should always be present with quantity 1 even if the value is 0, we also need to
                // ensure that calculated taxes exists (even if it has value 0).
                $previousTax = $summedUpShippingCosts->getCalculatedTaxes()->first();
                if ($previousTax) {
                    $calculatedTaxes->add(new CalculatedTax(0, $previousTax->getTaxRate(), 0));
                }
            }

            $summedUpShippingCosts = new CalculatedPrice(
                $summedUpShippingCosts->getUnitPrice() + $shippingCost->getUnitPrice(),
                $summedUpShippingCosts->getTotalPrice() + $shippingCost->getTotalPrice(),
                $calculatedTaxes,
                $summedUpShippingCosts->getTaxRules(), // We ignore and do not recalculate the tax rules for now.
                1, // quantity
                $summedUpShippingCosts->getReferencePrice(),
                $summedUpShippingCosts->getListPrice(),
            );
        }

        return $summedUpShippingCosts;
    }

    public function sumCalculatedTaxCollections(
        CalculatedTaxCollection $baseCalculatedTaxCollection,
        CalculatedTaxCollection ...$calculatedTaxCollections,
    ): CalculatedTaxCollection {
        /** @var CalculatedTaxCollection $summedUpCalculatedTaxCollection */
        $summedUpCalculatedTaxCollection = CalculatedTaxCollection::createFrom($baseCalculatedTaxCollection);

        foreach ($calculatedTaxCollections as $calculatedTaxCollection) {
            foreach ($calculatedTaxCollection->getKeys() as $taxRate) {
                $tax1 = $summedUpCalculatedTaxCollection->get($taxRate);
                $tax2 = $calculatedTaxCollection->get($taxRate);

                $taxTotal = $tax2->getTax() + ($tax1 ? $tax1->getTax() : 0);
                $priceTotal = $tax2->getPrice() + ($tax1 ? $tax1->getPrice() : 0);
                // If both taxes negate each other (i.e. their totals are 0), remove the item
                if (FloatComparator::equals($taxTotal, 0) && FloatComparator::equals($priceTotal, 0)) {
                    $summedUpCalculatedTaxCollection->remove($taxRate);

                    continue;
                }

                // Note that a calculated tax collection is mapped (key) by their tax rate. We can overwrite (update)
                // any existing calculated tax here by adding the same key again.
                $summedUpCalculatedTaxCollection->add(new CalculatedTax($taxTotal, $tax2->getTaxRate(), $priceTotal));
            }
        }

        return $summedUpCalculatedTaxCollection;
    }
}
