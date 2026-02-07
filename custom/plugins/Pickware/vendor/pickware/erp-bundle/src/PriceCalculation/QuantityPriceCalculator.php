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

use InvalidArgumentException;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

/**
 * See also Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator
 */
class QuantityPriceCalculator
{
    private GrossPriceCalculator $grossPriceCalculator;
    private NetPriceCalculator $netPriceCalculator;

    public function __construct(
        GrossPriceCalculator $grossPriceCalculator,
        NetPriceCalculator $netPriceCalculator,
    ) {
        $this->grossPriceCalculator = $grossPriceCalculator;
        $this->netPriceCalculator = $netPriceCalculator;
    }

    public function calculate(
        QuantityPriceDefinition $priceDefinition,
        PriceCalculationContext $priceCalculationContext,
    ): CalculatedPrice {
        $taxStatus = $priceCalculationContext->taxStatus;
        $itemRounding = $priceCalculationContext->itemRounding;
        switch ($priceCalculationContext->taxStatus) {
            case CartPrice::TAX_STATE_GROSS:
                $price = $this->grossPriceCalculator->calculate($priceDefinition, $itemRounding);
                break;
            case CartPrice::TAX_STATE_NET:
                $price = $this->netPriceCalculator->calculate($priceDefinition, $itemRounding);
                break;
            case CartPrice::TAX_STATE_FREE:
                $price = $this->netPriceCalculator->calculate($priceDefinition, $itemRounding);
                $price->assign([
                    'taxRules' => new TaxRuleCollection(),
                    'calculatedTaxes' => new CalculatedTaxCollection(),
                ]);
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    'Calculating price with tax status "%s" is not supported.',
                    $taxStatus,
                ));
        }

        return $price;
    }
}
