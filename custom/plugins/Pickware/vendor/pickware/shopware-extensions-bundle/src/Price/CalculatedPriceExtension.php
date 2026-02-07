<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CalculatedPriceExtension
{
    public static function equals(CalculatedPrice $price1, CalculatedPrice $price2): bool
    {
        if (
            ($price1->getQuantity() !== $price2->getQuantity())
            || (!FloatComparator::equals($price1->getTotalPrice(), $price2->getTotalPrice()))
            || (!FloatComparator::equals($price1->getUnitPrice(), $price2->getUnitPrice()))
        ) {
            return false;
        }
        if (!TaxRuleCollectionExtension::equals($price1->getTaxRules(), $price2->getTaxRules())) {
            return false;
        }
        if (!CalculatedTaxCollectionExtension::equals($price1->getCalculatedTaxes(), $price2->getCalculatedTaxes())) {
            return false;
        }

        return true;
    }
}
