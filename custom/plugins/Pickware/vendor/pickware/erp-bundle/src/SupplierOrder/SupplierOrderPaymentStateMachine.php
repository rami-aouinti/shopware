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

class SupplierOrderPaymentStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_erp_supplier_order.payment_state';
    public const STATE_PAID = 'paid';
    public const STATE_OPEN = 'open';
    public const STATE_PARTIALLY_PAID = 'partially_paid';

    public function __construct()
    {
        $open = new StateMachineState(self::STATE_OPEN, [
            'de-DE' => 'Offen',
            'en-GB' => 'Open',
        ]);
        $partiallyPaid = new StateMachineState(self::STATE_PARTIALLY_PAID, [
            'de-DE' => 'Teilweise bezahlt',
            'en-GB' => 'Partially paid',
        ]);
        $paid = new StateMachineState(self::STATE_PAID, [
            'de-DE' => 'Bezahlt',
            'en-GB' => 'Paid',
        ]);

        parent::__construct(self::TECHNICAL_NAME, [
            'de-DE' => 'Lieferantenbestellungszahlungsstatus',
            'en-GB' => 'Supplier order payment state',
        ], [
            $open,
            $partiallyPaid,
            $paid,
        ], $open);

        $this->addTransitionsFromAllStatesToState($open, 'open');
        $this->addTransitionsFromAllStatesToState($partiallyPaid, 'pay_partially');
        $this->addTransitionsFromAllStatesToState($paid, 'pay');
    }
}
