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
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CalculatedTaxCollectionExtension
{
    public static function equals(CalculatedTaxCollection $taxes1, CalculatedTaxCollection $taxes2): bool
    {
        if (!CollectionExtension::haveSameKeys($taxes1, $taxes2)) {
            return false;
        }

        foreach ($taxes1->getKeys() as $key) {
            $tax1 = $taxes1->get($key);
            $tax2 = $taxes2->get($key);

            if (!FloatComparator::equals($tax1->getTax(), $tax2->getTax())) {
                return false;
            }
            if (!FloatComparator::equals($tax1->getPrice(), $tax2->getPrice())) {
                return false;
            }
        }

        return true;
    }
}
