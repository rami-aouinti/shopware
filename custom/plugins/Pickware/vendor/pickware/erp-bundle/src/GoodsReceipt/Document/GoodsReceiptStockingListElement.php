<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Document;

use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class GoodsReceiptStockingListElement
{
    public function __construct(
        public readonly ProductEntity $product,
        public readonly ?WarehouseEntity $warehouse,
        public readonly ?BinLocationEntity $binLocation,
        public readonly int $quantity,
    ) {}
}
