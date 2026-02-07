<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Pickware\InstallationLibrary\StateMachine\StateMachine;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;

class GoodsReceiptStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_erp_goods_receipt.state';
    public const STATE_CREATED = 'created';
    public const STATE_APPROVED = 'approved';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_COMPLETED = 'completed';
    public const TRANSITION_APPROVE = 'approve';
    public const TRANSITION_START = 'start';
    public const TRANSITION_COMPLETE = 'complete';

    public function __construct()
    {
        $created = new StateMachineState(self::STATE_CREATED, [
            'de-DE' => 'Erfasst',
            'en-GB' => 'Created',
        ]);
        $approved = new StateMachineState(self::STATE_APPROVED, [
            'de-DE' => 'Freigegeben',
            'en-GB' => 'Approved',
        ]);
        $inProgress = new StateMachineState(self::STATE_IN_PROGRESS, [
            'de-DE' => 'In Bearbeitung',
            'en-GB' => 'In Progress',
        ]);
        $completed = new StateMachineState(self::STATE_COMPLETED, [
            'de-DE' => 'Abgeschlossen',
            'en-GB' => 'Completed',
        ]);

        $created->addTransitionToState($approved, self::TRANSITION_APPROVE);
        $approved->addTransitionToState($inProgress, self::TRANSITION_START);
        $approved->addTransitionToState($completed, self::TRANSITION_COMPLETE);
        $inProgress->addTransitionToState($completed, self::TRANSITION_COMPLETE);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Wareneingangsstatus',
                'en-GB' => 'Goods receipt state',
            ],
            [
                $created,
                $approved,
                $inProgress,
                $completed,
            ],
            $created,
        );
    }
}
