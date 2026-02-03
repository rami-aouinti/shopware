<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing;

use JsonSerializable;

class CashPointClosingTransactionPayment implements JsonSerializable
{
    private string $type;
    private string $currencyCode;
    private float $amount;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'currencyCode' => $this->currencyCode,
            'amount' => $this->amount,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->type = $array['type'];
        $self->currencyCode = $array['currencyCode'];
        $self->amount = $array['amount'];

        return $self;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }
}
