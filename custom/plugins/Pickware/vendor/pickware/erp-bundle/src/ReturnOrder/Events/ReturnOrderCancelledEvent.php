<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Events;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class ReturnOrderCancelledEvent extends Event
{
    public function __construct(
        private readonly string $returnOrderId,
        private readonly Context $context,
    ) {}

    public function getReturnOrderId(): string
    {
        return $this->returnOrderId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
