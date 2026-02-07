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
class TotalByPaymentType implements JsonSerializable
{
    public string $paymentType;
    public Price $total;

    /**
     * @var TotalByLineItemTypeGroup[] $lineItemTypeGroups
     */
    public array $lineItemTypeGroups;

    public function __construct(string $paymentType)
    {
        $this->paymentType = $paymentType;
        $this->total = Price::createEmpty();
        $this->lineItemTypeGroups = [];
    }

    public function jsonSerialize(): array
    {
        return [
            'paymentType' => $this->paymentType,
            'total' => $this->total,
            'lineItemTypeGroups' => $this->lineItemTypeGroups,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self($array['paymentType']);

        $self->total = Price::fromArray($array['total']);
        $self->lineItemTypeGroups = array_map(
            fn(array $lineItemGroup) => TotalByLineItemTypeGroup::fromArray($lineItemGroup),
            $array['lineItemTypeGroups'],
        );

        return $self;
    }

    public function add(LineItemTotal $lineItemTotal): void
    {
        $this->total->add($lineItemTotal->total);

        foreach ($this->lineItemTypeGroups as $lineItemGroup) {
            if ($lineItemGroup->lineItemTypeGroup === $lineItemTotal->getLineItemTypeGroup()) {
                $lineItemGroup->add($lineItemTotal);

                return;
            }
        }

        $totalByLineItemGroup = new TotalByLineItemTypeGroup($lineItemTotal->getLineItemTypeGroup());
        $totalByLineItemGroup->add($lineItemTotal);
        $this->lineItemTypeGroups[] = $totalByLineItemGroup;
        usort(
            $this->lineItemTypeGroups,
            fn(TotalByLineItemTypeGroup $a, TotalByLineItemTypeGroup $b) => strcmp($a->lineItemTypeGroup, $b->lineItemTypeGroup),
        );
    }
}
