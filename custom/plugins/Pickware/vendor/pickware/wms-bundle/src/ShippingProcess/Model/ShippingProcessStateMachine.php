<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ShippingProcess\Model;

use Pickware\InstallationLibrary\StateMachine\StateMachine;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;

class ShippingProcessStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_wms.shipping_process';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_DEFERRED = 'deferred';
    public const STATE_NEEDS_REVIEW = 'needs_review';
    public const STATE_COMPLETED = 'completed';
    public const STATE_CANCELED = 'cancelled';
    public const TRANSITION_DEFER = 'defer';
    public const TRANSITION_CONTINUE = 'continue';
    public const TRANSITION_COMPLETE = 'complete';
    public const TRANSITION_REQUEST_REVIEW = 'request_review';

    // Shopware versions < 6.7.0 do ALWAYS allow a state transitions called 'cancel'. To be compatible with 6.6.x we
    // use a different name for the transition. See:
    // https://github.com/shopware/shopware/blob/fde02b7dc7e7f3a8ad537ccea0663aa688db87eb/src/Core/System/StateMachine/StateMachineRegistry.php#L260-L266
    public const TRANSITION_CANCEL = 'cancel_';

    public function __construct()
    {
        $inProgress = new StateMachineState(self::STATE_IN_PROGRESS, [
            'de-DE' => 'In Bearbeitung',
            'en-GB' => 'In Progress',
        ]);
        $needsReview = new StateMachineState(self::STATE_NEEDS_REVIEW, [
            'de-DE' => 'Prüfung notwendig',
            'en-GB' => 'Needs Review',
        ]);
        $deferred = new StateMachineState(self::STATE_DEFERRED, [
            'de-DE' => 'Zurückgestellt',
            'en-GB' => 'Deferred',
        ]);
        $completed = new StateMachineState(self::STATE_COMPLETED, [
            'de-DE' => 'Abgeschlossen',
            'en-GB' => 'Completed',
        ]);
        $cancelled = new StateMachineState(self::STATE_CANCELED, [
            'de-DE' => 'Abgebrochen',
            'en-GB' => 'Cancelled',
        ]);

        $inProgress->addTransitionToState($deferred, self::TRANSITION_DEFER);
        $inProgress->addTransitionToState($completed, self::TRANSITION_COMPLETE);
        $inProgress->addTransitionToState($needsReview, self::TRANSITION_REQUEST_REVIEW);
        $inProgress->addTransitionToState($cancelled, self::TRANSITION_CANCEL);
        $deferred->addTransitionToState($inProgress, self::TRANSITION_CONTINUE);
        $deferred->addTransitionToState($needsReview, self::TRANSITION_REQUEST_REVIEW);
        $deferred->addTransitionToState($cancelled, self::TRANSITION_CANCEL);
        $needsReview->addTransitionToState($deferred, self::TRANSITION_DEFER);
        $needsReview->addTransitionToState($cancelled, self::TRANSITION_CANCEL);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Versandprozess',
                'en-GB' => 'Shipping process',
            ],
            [
                $inProgress,
                $deferred,
                $needsReview,
                $completed,
                $cancelled,
            ],
            $deferred,
        );
    }
}
