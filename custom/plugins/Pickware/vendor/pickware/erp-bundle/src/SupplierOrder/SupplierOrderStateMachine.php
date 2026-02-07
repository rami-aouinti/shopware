<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Pickware\InstallationLibrary\StateMachine\StateMachine;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;

class SupplierOrderStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_erp_supplier_order.order_state';
    public const STATE_PARTIALLY_DELIVERED = 'partially_delivered';
    public const STATE_DELIVERED = 'delivered';
    public const STATE_OPEN = 'open';
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_SENT = 'sent';
    public const STATE_COMPLETED = 'completed';
    public const STATE_CANCELLED = 'cancelled';
    public const TRANSITION_DELIVER_PARTIALLY = 'deliver_partially';
    public const TRANSITION_DELIVER = 'deliver';
    public const TRANSITION_OPEN = 'open';
    public const TRANSITION_CONFIRM = 'confirm';
    public const TRANSITION_SEND_TO_SUPPLIER = 'send_to_supplier';
    public const TRANSITION_COMPLETE = 'complete';
    public const TRANSITION_CANCEL = 'cancel';

    public function __construct()
    {
        $partiallyDelivered = new StateMachineState(self::STATE_PARTIALLY_DELIVERED, [
            'de-DE' => 'Teilweise geliefert',
            'en-GB' => 'Partially delivered',
        ]);
        $delivered = new StateMachineState(self::STATE_DELIVERED, [
            'de-DE' => 'Geliefert',
            'en-GB' => 'Delivered',
        ]);
        $open = new StateMachineState(self::STATE_OPEN, [
            'de-DE' => 'Offen',
            'en-GB' => 'Open',
        ]);
        $confirmed = new StateMachineState(self::STATE_CONFIRMED, [
            'de-DE' => 'Bestätigt',
            'en-GB' => 'Confirmed',
        ]);
        $sent = new StateMachineState(self::STATE_SENT, [
            'de-DE' => 'An Lieferanten gesendet',
            'en-GB' => 'Sent to supplier',
        ]);
        $completed = new StateMachineState(self::STATE_COMPLETED, [
            'de-DE' => 'Abgeschlossen',
            'en-GB' => 'Completed',
        ]);
        $cancelled = new StateMachineState(self::STATE_CANCELLED, [
            'de-DE' => 'Storniert',
            'en-GB' => 'Cancelled',
        ]);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Lieferantenbestellstatus',
                'en-GB' => 'Supplier order state',
            ],
            [
                $partiallyDelivered,
                $delivered,
                $open,
                $confirmed,
                $sent,
                $completed,
                $cancelled,
            ],
            $open,
        );

        $this->addTransitionsFromAllStatesToState($partiallyDelivered, self::TRANSITION_DELIVER_PARTIALLY);
        $this->addTransitionsFromAllStatesToState($delivered, self::TRANSITION_DELIVER);
        $this->addTransitionsFromAllStatesToState($open, self::TRANSITION_OPEN);
        $this->addTransitionsFromAllStatesToState($confirmed, self::TRANSITION_CONFIRM);
        $this->addTransitionsFromAllStatesToState($sent, self::TRANSITION_SEND_TO_SUPPLIER);
        $this->addTransitionsFromAllStatesToState($completed, self::TRANSITION_COMPLETE);
        $this->addTransitionsFromAllStatesToState($cancelled, self::TRANSITION_CANCEL);
    }
}
