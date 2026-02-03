<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ShipPickingProcessBeforeOrderStateChangeToCompleteEvent extends Event implements ShopwareEvent
{
    public const EVENT_NAME = 'pickware_wms.picking_process.before_order_state_change_to_complete';

    private string $pickingProcessId;
    private Context $context;
    private bool $skipOrderStateTransitionToComplete = false;

    public function __construct(string $pickingProcessId, Context $context)
    {
        $this->pickingProcessId = $pickingProcessId;
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPickingProcessId(): string
    {
        return $this->pickingProcessId;
    }

    public function setSkipOrderStateTransitionToComplete(bool $skipOrderStateTransitionToComplete): void
    {
        $this->skipOrderStateTransitionToComplete = $skipOrderStateTransitionToComplete;
    }

    public function getSkipOrderStateTransitionToComplete(): bool
    {
        return $this->skipOrderStateTransitionToComplete;
    }
}
