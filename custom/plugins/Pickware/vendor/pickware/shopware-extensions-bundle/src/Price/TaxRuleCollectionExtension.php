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

use Pickware\ShopwareExtensionsBundle\Struct\CollectionExtension;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class TaxRuleCollectionExtension
{
    public static function equals(TaxRuleCollection $taxRules1, TaxRuleCollection $taxRules2): bool
    {
        if (!CollectionExtension::haveSameKeys($taxRules1, $taxRules2)) {
            return false;
        }

        foreach ($taxRules1->getKeys() as $key) {
            $taxRule1 = $taxRules1->get($key);
            $taxRule2 = $taxRules2->get($key);

            if (!FloatComparator::equals($taxRule1->getPercentage(), $taxRule2->getPercentage())) {
                return false;
            }
        }

        return true;
    }
}
