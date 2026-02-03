<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends ImmutableCollection<GoodsReceiptStockItem>
 */
#[Exclude]
class GoodsReceiptStockItemCollection extends ImmutableCollection
{
    /**
     * Returns a new collection with all items that have the same order ID, product ID, and batch ID combined into one.
     */
    public function collapse(): self
    {
        return new self($this->groupBy(
            fn(GoodsReceiptStockItem $item) => sprintf(
                '%s-%s-%s',
                $item->getProductId(),
                $item->getBatchId() ?? '',
                $item->getOrderId() ?? '',
            ),
            fn(ImmutableCollection $group) => new GoodsReceiptStockItem(
                productId: $group->first()->getProductId(),
                batchId: $group->first()->getBatchId(),
                quantity: $group->map(fn(GoodsReceiptStockItem $item) => $item->getQuantity())->sum(),
                orderId: $group->first()->getOrderId(),
            ),
        ));
    }
}
