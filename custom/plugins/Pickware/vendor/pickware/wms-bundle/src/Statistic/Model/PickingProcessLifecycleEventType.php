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

use http\Exception\InvalidArgumentException;
use Pickware\PickwareWms\PickingProcess\PickingProcessStateMachine;

enum PickingProcessLifecycleEventType: string
{
    case Cancel = PickingProcessStateMachine::TRANSITION_CANCEL;
    case Complete = PickingProcessStateMachine::TRANSITION_COMPLETE;
    case Continue = PickingProcessStateMachine::TRANSITION_CONTINUE;
    case Create = 'create';
    case Defer = PickingProcessStateMachine::TRANSITION_DEFER;
    case TakeOver = 'take_over';

    public static function fromPickingProcessStateTransition(string $transition): self
    {
        return match ($transition) {
            PickingProcessStateMachine::TRANSITION_CANCEL => self::Cancel,
            PickingProcessStateMachine::TRANSITION_COMPLETE => self::Complete,
            PickingProcessStateMachine::TRANSITION_CONTINUE => self::Continue,
            PickingProcessStateMachine::TRANSITION_DEFER => self::Defer,
            default => throw new InvalidArgumentException(sprintf("Transition '%s' is not a valid picking process lifecycle event type", $transition)),
        };
    }
}
