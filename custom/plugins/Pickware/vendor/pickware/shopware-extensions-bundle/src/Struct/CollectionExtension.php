<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Struct;

use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CollectionExtension
{
    public static function haveSameKeys(Collection $collection1, Collection $collection2): bool
    {
        if ($collection1->count() !== $collection2->count()) {
            return false;
        }
        foreach ($collection1->getKeys() as $key) {
            if (!$collection2->has($key)) {
                return false;
            }
        }

        // Since the number of keys is the same, and all keys in collection 1 are in collection 2, both collections
        // share the same keys.
        return true;
    }
}
