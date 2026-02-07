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
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class TotalByLineItemType implements JsonSerializable
{
    public string $lineItemType;
    public Price $total;

    /**
     * @var TotalByTaxRate[] $taxRates
     */
    public array $taxRates;

    public function __construct(string $lineItemType)
    {
        $this->lineItemType = $lineItemType;
        $this->total = Price::createEmpty();
        $this->taxRates = [];
    }

    public function jsonSerialize(): array
    {
        return [
            'lineItemType' => $this->lineItemType,
            'total' => $this->total,
            'taxRates' => $this->taxRates,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self($array['lineItemType']);

        $self->total = Price::fromArray($array['total']);
        $self->taxRates = array_map(
            fn(array $taxRate) => TotalByTaxRate::fromArray($taxRate),
            $array['taxRates'],
        );

        return $self;
    }

    public function add(LineItemTotal $lineItemTotal): void
    {
        $this->total->add($lineItemTotal->total);

        foreach ($this->taxRates as $totalByTaxRate) {
            if (FloatComparator::equals($totalByTaxRate->taxRate, $lineItemTotal->taxRate)) {
                $totalByTaxRate->add($lineItemTotal->total);

                return;
            }
        }

        $totalByTaxRate = new TotalByTaxRate($lineItemTotal->taxRate);
        $totalByTaxRate->add($lineItemTotal->total);
        $this->taxRates[] = $totalByTaxRate;
        usort(
            $this->taxRates,
            fn(TotalByTaxRate $a, TotalByTaxRate $b) => $a->taxRate - $b->taxRate,
        );
    }
}
