<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PriceCalculation;

use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class PriceCalculator
{
    public function __construct(
        private readonly QuantityPriceCalculator $quantityPriceCalculator,
    ) {}

    public function calculateQuantityPrice(
        QuantityPriceDefinition $priceDefinition,
        PriceCalculationContext $priceCalculationContext,
    ): CalculatedPrice {
        return $this->quantityPriceCalculator->calculate($priceDefinition, $priceCalculationContext);
    }

    public function calculateAbsolutePrice(
        AbsolutePriceDefinition $absolutePriceDefinition,
        int $quantity,
        TaxRuleCollection $taxRules,
        PriceCalculationContext $priceCalculationContext,
    ): CalculatedPrice {
        $quantityPriceDefinition = new QuantityPriceDefinition($absolutePriceDefinition->getPrice(), $taxRules, $quantity);

        return $this->quantityPriceCalculator->calculate($quantityPriceDefinition, $priceCalculationContext);
    }
}
