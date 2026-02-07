<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\CustomAggregation;

use JsonSerializable;
use Pickware\PickwarePos\CashPointClosing\Price;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class TotalByTaxRate implements JsonSerializable
{
    public float $taxRate;
    public Price $total;

    public function __construct(float $taxRate)
    {
        $this->taxRate = $taxRate;
        $this->total = Price::createEmpty();
    }

    public function jsonSerialize(): array
    {
        return [
            'taxRate' => $this->taxRate,
            'total' => $this->total,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self((float) $array['taxRate']);

        $self->total = Price::fromArray($array['total']);

        return $self;
    }

    public function add(Price $total): void
    {
        $this->total->add($total);
    }
}
