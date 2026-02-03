<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use JsonSerializable;
use Pickware\MoneyBundle\MoneyValue;

class Fee implements JsonSerializable
{
    public function __construct(
        private readonly FeeType $type,
        private readonly MoneyValue $amount,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'amount' => $this->amount,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            type: FeeType::from($array['type']),
            amount: MoneyValue::fromArray($array['amount']),
        );
    }

    public function getType(): FeeType
    {
        return $this->type;
    }

    public function getAmount(): MoneyValue
    {
        return $this->amount;
    }
}
