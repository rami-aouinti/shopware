<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<GoodsReceiptTagEntity>
 */
class GoodsReceiptTagCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'pickware_erp_goods_receipt_tag_collection';
    }

    protected function getExpectedClass(): string
    {
        return GoodsReceiptTagEntity::class;
    }
}
