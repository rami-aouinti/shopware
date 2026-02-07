<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Model;

use InvalidArgumentException;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;

enum DeliveryLifecycleEventType: string
{
    case Cancel = DeliveryStateMachine::TRANSITION_CANCEL;
    case Complete = DeliveryStateMachine::TRANSITION_COMPLETE;
    case Create = 'create';
    case CreateDocuments = DeliveryStateMachine::TRANSITION_CREATE_DOCUMENTS;
    case Pack = DeliveryStateMachine::TRANSITION_PACK;
    case Ship = DeliveryStateMachine::TRANSITION_SHIP;

    public static function fromDeliveryStateTransition(string $transition): self
    {
        return match ($transition) {
            DeliveryStateMachine::TRANSITION_CANCEL => self::Cancel,
            DeliveryStateMachine::TRANSITION_COMPLETE => self::Complete,
            DeliveryStateMachine::TRANSITION_CREATE_DOCUMENTS => self::CreateDocuments,
            DeliveryStateMachine::TRANSITION_PACK => self::Pack,
            DeliveryStateMachine::TRANSITION_SHIP => self::Ship,
            default => throw new InvalidArgumentException(sprintf("Transition '%s' is not a valid delivery lifecycle event type", $transition)),
        };
    }
}
