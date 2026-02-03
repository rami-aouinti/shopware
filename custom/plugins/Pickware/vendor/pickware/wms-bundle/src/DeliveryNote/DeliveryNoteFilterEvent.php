<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DeliveryNote;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class DeliveryNoteFilterEvent
{
    public function __construct(
        private OrderEntity $order,
        private readonly Context $context,
    ) {
        trigger_error('This event is deprecated use DeliveryNoteLineItemFilterEvent instead', E_USER_DEPRECATED);
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
