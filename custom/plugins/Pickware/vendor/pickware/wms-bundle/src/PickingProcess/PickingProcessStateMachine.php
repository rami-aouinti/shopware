<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\InstallationLibrary\StateMachine\StateMachine;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;

class PickingProcessStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_wms.picking_process';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_DEFERRED = 'deferred';
    public const STATE_PICKED = 'picked';
    public const STATE_CANCELLED = 'cancelled';
    public const CONCLUDED_STATES = [
        self::STATE_PICKED,
        self::STATE_CANCELLED,
    ];
    public const TRANSITION_COMPLETE = 'complete';

    // We cannot use 'cancel' as the name for the transition, as Shopware ALWAYS allows to cancel anything. See:
    // https://github.com/shopware/shopware/blob/c9b311153a79a78110ddbb65e649b8dc4a4574af/src/Core/System/StateMachine/StateMachineRegistry.php#L296
    public const TRANSITION_CANCEL = 'cancel_';
    public const TRANSITION_DEFER = 'defer';
    public const TRANSITION_CONTINUE = 'continue';

    public function __construct()
    {
        $inProgress = new StateMachineState(self::STATE_IN_PROGRESS, [
            'de-DE' => 'In Bearbeitung',
            'en-GB' => 'In Progress',
        ]);
        $deferred = new StateMachineState(self::STATE_DEFERRED, [
            'de-DE' => 'Zurückgestellt',
            'en-GB' => 'Deferred',
        ]);
        $picked = new StateMachineState(self::STATE_PICKED, [
            'de-DE' => 'Kommissioniert',
            'en-GB' => 'Picked',
        ]);
        $cancelled = new StateMachineState(self::STATE_CANCELLED, [
            'de-DE' => 'Abgebrochen',
            'en-GB' => 'Cancelled',
        ]);

        $inProgress->addTransitionToState($picked, self::TRANSITION_COMPLETE);
        $inProgress->addTransitionToState($cancelled, self::TRANSITION_CANCEL);
        $inProgress->addTransitionToState($deferred, self::TRANSITION_DEFER);
        $deferred->addTransitionToState($inProgress, self::TRANSITION_CONTINUE);
        $deferred->addTransitionToState($cancelled, self::TRANSITION_CANCEL);
        $picked->addTransitionToState($cancelled, self::TRANSITION_CANCEL);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Kommissionierung',
                'en-GB' => 'Picking process',
            ],
            [
                $inProgress,
                $deferred,
                $picked,
                $cancelled,
            ],
            $deferred,
        );
    }
}
