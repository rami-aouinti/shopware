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

class PriceNegator
{
    public function negateCalculatedPrice(CalculatedPrice $price): CalculatedPrice
    {
        return new CalculatedPrice(
            $price->getUnitPrice(),
            -1 * $price->getTotalPrice(),
            $this->negateCalculatedTaxes($price->getCalculatedTaxes()),
            $price->getTaxRules(), // Tax rules do not need to be negated
            -1 * $price->getQuantity(),
            $price->getReferencePrice(),
            $price->getListPrice(),
        );
    }

    /**
     * Creates and returns a new and negated ShippingCosts CalculatedPrice based on the given CalculatedPrice. Note that
     * the quantity for shipping costs stays the same (1) whereas the unit and total prices are negated. That is why
     * this function is named specifically for calculating shipping costs.
     */
    public function negateShippingCosts(CalculatedPrice $shippingCosts): CalculatedPrice
    {
        if ($shippingCosts->getQuantity() !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Negating costs only works for calculated prices with quantity 1. Found quantity: %s.',
                $shippingCosts->getQuantity(),
            ));
        }

        return new CalculatedPrice(
            -1 * $shippingCosts->getUnitPrice(),
            -1 * $shippingCosts->getTotalPrice(),
            $this->negateCalculatedTaxes($shippingCosts->getCalculatedTaxes()),
            $shippingCosts->getTaxRules(), // Tax rules do not need to be negated
            1, // quantity
            $shippingCosts->getReferencePrice(),
            $shippingCosts->getListPrice(),
        );
    }

    /**
     * Creates and returns a new and negated CalculatedTaxCollection based on the given CalculatedTaxCollection.
     */
    public function negateCalculatedTaxes(CalculatedTaxCollection $calculatedTaxes): CalculatedTaxCollection
    {
        $negatedCalculatedTaxes = new CalculatedTaxCollection();
        foreach ($calculatedTaxes as $tax) {
            $negatedCalculatedTaxes->add(new CalculatedTax(
                -1 * $tax->getTax(),
                $tax->getTaxRate(),
                -1 * $tax->getPrice(),
            ));
        }

        return $negatedCalculatedTaxes;
    }

    /**
     * Creates and returns a new and negated CartPrice based on the given CartPrice.
     */
    public function negateCartPrice(CartPrice $cartPrice): CartPrice
    {
        /** @var CartPrice $originalCartPrice */
        $originalCartPrice = CartPrice::createFrom($cartPrice);

        return new CartPrice(
            -1 * $originalCartPrice->getNetPrice(),
            -1 * $originalCartPrice->getTotalPrice(),
            -1 * $originalCartPrice->getPositionPrice(),
            $this->negateCalculatedTaxes($cartPrice->getCalculatedTaxes()),
            $cartPrice->getTaxRules(), // TaxRules (TaxRate and percentage) are not changed
            $cartPrice->getTaxStatus(),
            -1 * $cartPrice->getRawTotal(),
        );
    }
}
