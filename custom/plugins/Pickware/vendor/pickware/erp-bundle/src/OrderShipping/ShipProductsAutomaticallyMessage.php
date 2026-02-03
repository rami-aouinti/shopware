<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class ShipProductsAutomaticallyMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly array $orderIds,
        private readonly Context $context,
    ) {}

    public function getOrderIds(): array
    {
        return $this->orderIds;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
