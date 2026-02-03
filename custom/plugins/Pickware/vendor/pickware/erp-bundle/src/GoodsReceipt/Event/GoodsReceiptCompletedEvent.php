<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Event;

use Shopware\Core\Framework\Context;

class GoodsReceiptCompletedEvent
{
    public function __construct(
        private readonly string $goodsReceiptId,
        private readonly Context $context,
    ) {}

    public function getGoodsReceiptId(): string
    {
        return $this->goodsReceiptId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
