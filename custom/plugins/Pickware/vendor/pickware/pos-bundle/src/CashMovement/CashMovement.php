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

use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CashMovement
{
    public function __construct(
        private readonly string $uniqueIdentifier,
        private readonly float $amount,
        private readonly string $currencyId,
        private readonly CashMovementType $type,
        private readonly string $salesChannelId,
        private readonly string $branchStoreName,
        private readonly string $cashRegisterName,
        private readonly DateTimeInterface $date,
        private readonly ?string $comment,
    ) {}

    public function getUniqueIdentifier(): string
    {
        return $this->uniqueIdentifier;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function getType(): CashMovementType
    {
        return $this->type;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getBranchStoreName(): string
    {
        return $this->branchStoreName;
    }

    public function getCashRegisterName(): string
    {
        return $this->cashRegisterName;
    }

    public function getDate(): DateTimeInterface
    {
        return $this->date;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }
}
