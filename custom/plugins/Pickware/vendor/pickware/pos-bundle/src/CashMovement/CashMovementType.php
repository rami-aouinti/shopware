<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashMovement;

use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionLineItemDefinition;

enum CashMovementType
{
    case Deposit;
    case Withdrawal;

    public static function fromSerializedLineItemType(string $serializedLineItemType): self
    {
        return match ($serializedLineItemType) {
            CashPointClosingTransactionLineItemDefinition::TYPE_EINZAHLUNG => self::Deposit,
            CashPointClosingTransactionLineItemDefinition::TYPE_AUSZAHLUNG => self::Withdrawal,
        };
    }
}
