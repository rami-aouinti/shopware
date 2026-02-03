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

class DeliveryStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_wms.delivery';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_PICKED = 'picked';
    public const STATE_DOCUMENTS_CREATED = 'documents_created';
    public const STATE_PACKED = 'packed';
    public const STATE_SHIPPED = 'shipped';
    public const STATE_CANCELLED = 'cancelled';
    public const PENDING_STATES = [
        self::STATE_IN_PROGRESS,
        self::STATE_PICKED,
        self::STATE_DOCUMENTS_CREATED,
        self::STATE_PACKED,
    ];
    public const CONCLUDED_STATES = [
        self::STATE_SHIPPED,
        self::STATE_CANCELLED,
    ];
    public const TRANSITION_COMPLETE = 'complete';
    public const TRANSITION_CREATE_DOCUMENTS = 'create_documents';
    public const TRANSITION_PACK = 'pack';
    public const TRANSITION_SHIP = 'ship';

    // We cannot use 'cancel' as the name for the transition, as Shopware ALWAYS allows to cancel anything. See:
    // https://github.com/shopware/shopware/blob/c9b311153a79a78110ddbb65e649b8dc4a4574af/src/Core/System/StateMachine/StateMachineRegistry.php#L296
    public const TRANSITION_CANCEL = 'cancel_';

    public function __construct()
    {
        $inProgress = new StateMachineState(self::STATE_IN_PROGRESS, [
            'de-DE' => 'In Bearbeitung',
            'en-GB' => 'In Progress',
        ]);
        $picked = new StateMachineState(self::STATE_PICKED, [
            'de-DE' => 'Kommissioniert',
            'en-GB' => 'Picked',
        ]);
        $documentsCreated = new StateMachineState(self::STATE_DOCUMENTS_CREATED, [
            'de-DE' => 'Dokumente erstellt',
            'en-GB' => 'Documents created',
        ]);
        $packed = new StateMachineState(self::STATE_PACKED, [
            'de-DE' => 'Verpackt',
            'en-GB' => 'Packed',
        ]);
        $shipped = new StateMachineState(self::STATE_SHIPPED, [
            'de-DE' => 'Versandt',
            'en-GB' => 'Shipped',
        ]);
        $cancelled = new StateMachineState(self::STATE_CANCELLED, [
            'de-DE' => 'Abgebrochen',
            'en-GB' => 'Cancelled',
        ]);

        $inProgress->addTransitionToState($picked, self::TRANSITION_COMPLETE);
        $inProgress->addTransitionToState($cancelled, self::TRANSITION_CANCEL);
        $picked->addTransitionToState($documentsCreated, self::TRANSITION_CREATE_DOCUMENTS);
        $picked->addTransitionToState($cancelled, self::TRANSITION_CANCEL);
        $documentsCreated->addTransitionToState($cancelled, self::TRANSITION_CANCEL);
        $documentsCreated->addTransitionToState($packed, self::TRANSITION_PACK);
        $packed->addTransitionToState($shipped, self::TRANSITION_SHIP);
        $packed->addTransitionToState($cancelled, self::TRANSITION_CANCEL);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Lieferung',
                'en-GB' => 'Delivery',
            ],
            [
                $inProgress,
                $picked,
                $documentsCreated,
                $packed,
                $shipped,
                $cancelled,
            ],
            $inProgress,
        );
    }
}
