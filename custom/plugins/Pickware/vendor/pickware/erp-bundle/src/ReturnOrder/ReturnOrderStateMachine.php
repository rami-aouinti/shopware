<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use Pickware\InstallationLibrary\StateMachine\StateMachine;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;

class ReturnOrderStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_erp_return_order.state';
    public const STATE_REQUESTED = 'requested';
    public const STATE_ANNOUNCED = 'announced';
    public const STATE_RECEIVED = 'received';
    public const STATE_PARTIALLY_RECEIVED = 'partially_received';
    public const STATE_COMPLETED = 'completed';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_DECLINED = 'declined';
    public const TRANSITION_APPROVE = 'approve';
    public const TRANSITION_COMPLETE = 'complete';
    public const TRANSITION_RECEIVE = 'receive';
    public const TRANSITION_RECEIVE_PARTIALLY = 'receive_partially';
    public const TRANSITION_CANCEL = 'cancel';
    public const TRANSITION_DECLINE = 'decline';

    public function __construct()
    {
        $requested = new StateMachineState(self::STATE_REQUESTED, [
            'de-DE' => 'Angefragt',
            'en-GB' => 'Requested',
        ]);
        $announced = new StateMachineState(self::STATE_ANNOUNCED, [
            'de-DE' => 'Angekündigt',
            'en-GB' => 'Announced',
        ]);
        $partiallyReceived = new StateMachineState(self::STATE_PARTIALLY_RECEIVED, [
            'de-DE' => 'Teilweise empfangen',
            'en-GB' => 'Partially received',
        ]);
        $received = new StateMachineState(self::STATE_RECEIVED, [
            'de-DE' => 'Empfangen',
            'en-GB' => 'Received',
        ]);
        $completed = new StateMachineState(self::STATE_COMPLETED, [
            'de-DE' => 'Abgeschlossen',
            'en-GB' => 'Completed',
        ]);
        $cancelled = new StateMachineState(self::STATE_CANCELLED, [
            'de-DE' => 'Storniert',
            'en-GB' => 'Cancelled',
        ]);
        $declined = new StateMachineState(self::STATE_DECLINED, [
            'de-DE' => 'Abgelehnt',
            'en-GB' => 'Declined',
        ]);

        $requested->addTransitionToState($announced, self::TRANSITION_APPROVE);
        $requested->addTransitionToState($declined, self::TRANSITION_DECLINE);
        $announced->addTransitionToState($partiallyReceived, self::TRANSITION_RECEIVE_PARTIALLY);
        $announced->addTransitionToState($received, self::TRANSITION_RECEIVE);
        $partiallyReceived->addTransitionToState($received, self::TRANSITION_RECEIVE);
        $received->addTransitionToState($completed, self::TRANSITION_COMPLETE);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Rückgabe',
                'en-GB' => 'Return',
            ],
            [
                $requested,
                $announced,
                $partiallyReceived,
                $received,
                $completed,
                $cancelled,
                $declined,
            ],
            $requested,
        );

        $this->addTransitionsFromAllStatesToState($cancelled, self::TRANSITION_CANCEL);
    }
}
