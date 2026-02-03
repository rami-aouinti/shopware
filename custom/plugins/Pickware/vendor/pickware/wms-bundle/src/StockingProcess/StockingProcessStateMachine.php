<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess;

use Pickware\InstallationLibrary\StateMachine\StateMachine;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;

class StockingProcessStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_wms.stocking_process';
    public const STATE_CREATED = 'created';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_DEFERRED = 'deferred';
    public const STATE_COMPLETED = 'completed';
    public const TRANSITION_START = 'start';
    public const TRANSITION_DEFER = 'defer';
    public const TRANSITION_CONTINUE = 'continue';
    public const TRANSITION_COMPLETE = 'complete';

    public function __construct()
    {
        $created = new StateMachineState(self::STATE_CREATED, [
            'de-DE' => 'Erstellt',
            'en-GB' => 'Created',
        ]);
        $inProgress = new StateMachineState(self::STATE_IN_PROGRESS, [
            'de-DE' => 'In Bearbeitung',
            'en-GB' => 'In Progress',
        ]);
        $deferred = new StateMachineState(self::STATE_DEFERRED, [
            'de-DE' => 'Zurückgestellt',
            'en-GB' => 'Deferred',
        ]);
        $completed = new StateMachineState(self::STATE_COMPLETED, [
            'de-DE' => 'Abgeschlossen',
            'en-GB' => 'Completed',
        ]);

        $created->addTransitionToState($inProgress, self::TRANSITION_START);
        $inProgress->addTransitionToState($deferred, self::TRANSITION_DEFER);
        $inProgress->addTransitionToState($completed, self::TRANSITION_COMPLETE);
        $deferred->addTransitionToState($inProgress, self::TRANSITION_CONTINUE);
        $deferred->addTransitionToState($completed, self::TRANSITION_COMPLETE);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Einlagerungsvorgang',
                'en-GB' => 'Stocking process',
            ],
            [
                $created,
                $inProgress,
                $deferred,
                $completed,
            ],
            $created,
        );
    }
}
