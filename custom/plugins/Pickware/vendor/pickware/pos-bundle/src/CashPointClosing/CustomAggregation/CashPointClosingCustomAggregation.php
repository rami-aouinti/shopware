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

/**
 * The CashPointClosingCustomAggregation is a tree of calculated totals grouped by different properties where each node
 * holds the totals of all nodes in its subtree. The tree depth is a group-by property at each level:
 *
 * CashPointClosingCustomAggregation (root, no total)
 * - Per payment type (bar, unbar, ...)
 *   - Per line item group (sales, cash movements)
 *     - Per line item type (Umsatz, Rabatt, ...)
 *       - Per tax rate (19%, 7%, ...)
 */
class CashPointClosingCustomAggregation implements JsonSerializable
{
    /**
     * @var TotalByPaymentType[] $paymentTypes
     */
    public array $paymentTypes;

    public function jsonSerialize(): array
    {
        return [
            'paymentTypes' => $this->paymentTypes,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->paymentTypes = array_map(
            fn(array $paymentType) => TotalByPaymentType::fromArray($paymentType),
            $array['paymentTypes'] ?? [],
        );

        return $self;
    }

    public function add(LineItemTotal $lineItemTotal): void
    {
        foreach ($this->paymentTypes as &$paymentType) {
            if ($paymentType->paymentType === $lineItemTotal->paymentType) {
                $paymentType->add($lineItemTotal);

                return;
            }
        }

        $totalByPaymentType = new TotalByPaymentType($lineItemTotal->paymentType);
        $totalByPaymentType->add($lineItemTotal);
        $this->paymentTypes[] = $totalByPaymentType;
        usort(
            $this->paymentTypes,
            fn(TotalByPaymentType $a, TotalByPaymentType $b) => strcmp($a->paymentType, $b->paymentType),
        );
    }
}
