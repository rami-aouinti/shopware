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
class TotalByLineItemTypeGroup implements JsonSerializable
{
    public string $lineItemTypeGroup;
    public Price $total;

    /**
     * @var TotalByLineItemType[] $lineItemTypes
     */
    public array $lineItemTypes;

    public function __construct(string $lineItemTypeGroup)
    {
        $this->lineItemTypeGroup = $lineItemTypeGroup;
        $this->total = Price::createEmpty();
        $this->lineItemTypes = [];
    }

    public function jsonSerialize(): array
    {
        return [
            'lineItemTypeGroup' => $this->lineItemTypeGroup,
            'total' => $this->total,
            'lineItemTypes' => $this->lineItemTypes,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self($array['lineItemTypeGroup']);

        $self->total = Price::fromArray($array['total']);
        $self->lineItemTypes = array_map(
            fn(array $lineItemType) => TotalByLineItemType::fromArray($lineItemType),
            $array['lineItemTypes'],
        );

        return $self;
    }

    public function add(LineItemTotal $lineItemTotal): void
    {
        $this->total->add($lineItemTotal->total);

        foreach ($this->lineItemTypes as $lineItemType) {
            if ($lineItemType->lineItemType === $lineItemTotal->lineItemType) {
                $lineItemType->add($lineItemTotal);

                return;
            }
        }

        $totalByLineItemType = new TotalByLineItemType($lineItemTotal->lineItemType);
        $totalByLineItemType->add($lineItemTotal);
        $this->lineItemTypes[] = $totalByLineItemType;
        usort(
            $this->lineItemTypes,
            fn(TotalByLineItemType $a, TotalByLineItemType $b) => strcmp($a->lineItemType, $b->lineItemType),
        );
    }
}
