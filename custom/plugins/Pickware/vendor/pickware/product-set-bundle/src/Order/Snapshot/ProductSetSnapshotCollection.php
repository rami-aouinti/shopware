<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Order\Snapshot;

use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;

/**
 * @extends ImmutableCollection<ProductSetSnapshot>
 */
class ProductSetSnapshotCollection extends ImmutableCollection
{
    /**
     * @param CountingMap<string> $lineItemQuantities
     * @return CountingMap<string>
     */
    public function replaceChildLineItemsWithParentLineItems(CountingMap $lineItemQuantities): CountingMap
    {
        foreach ($this as $productSetSnapshot) {
            $lineItemQuantities = $productSetSnapshot->replaceChildLineItemsWithParentLineItem($lineItemQuantities);
        }

        return $lineItemQuantities;
    }

    /**
     * @return array<string>
     */
    public function getChildLineItemIds(): array
    {
        $childLineItemIds = [];
        foreach ($this as $productSetSnapshot) {
            $childLineItemIds = [
                ...$childLineItemIds,
                ...$productSetSnapshot->getChildOrderLineItemQuantities()->getKeys(),
            ];
        }

        return $childLineItemIds;
    }
}
