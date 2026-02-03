<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosPayment;

use Pickware\DatevBundle\Config\AccountAssignment\Item\CashMovementRequestItem;
use Pickware\PickwarePos\CashMovement\CashMovement;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosCashMovementRequestItem
{
    public function __construct(
        private readonly CashMovement $cashMovement,
        private readonly CashMovementRequestItem $accountRequestItem,
        private readonly CashMovementRequestItem $contraAccountRequestItem,
    ) {}

    public function getCashMovement(): CashMovement
    {
        return $this->cashMovement;
    }

    public function getAccountRequestItem(): CashMovementRequestItem
    {
        return $this->accountRequestItem;
    }

    public function getContraAccountRequestItem(): CashMovementRequestItem
    {
        return $this->contraAccountRequestItem;
    }
}
