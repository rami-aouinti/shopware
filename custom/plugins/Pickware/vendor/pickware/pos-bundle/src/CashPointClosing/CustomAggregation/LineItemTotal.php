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

use Exception;
use JsonSerializable;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionLineItemDefinition;
use Pickware\PickwarePos\CashPointClosing\Price;

class LineItemTotal implements JsonSerializable
{
    public const LINE_ITEM_GROUP_SALES = 'sales';
    public const LINE_ITEM_GROUP_CASH_MOVEMENTS = 'cashMovements';
    public const LINE_ITEM_GROUP_SALES_TYPES = [
        CashPointClosingTransactionLineItemDefinition::TYPE_UMSATZ,
        CashPointClosingTransactionLineItemDefinition::TYPE_PFAND,
        CashPointClosingTransactionLineItemDefinition::TYPE_PFANDRUECKZAHLUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_MEHRZWECKGUTSCHEINKAUF,
        CashPointClosingTransactionLineItemDefinition::TYPE_MEHRZWECKGUTSCHEINEINLOESUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_EINZWECKGUTSCHEINKAUF,
        CashPointClosingTransactionLineItemDefinition::TYPE_EINZWECKGUTSCHEINEINLOESUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_FORDERUNGSENTSTEHUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_FORDERUNGSAUFLOESUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_ANZAHLUNGSEINSTELLUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_ANZAHLUNGSAUFLOESUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_TRINKGELDAG,
        CashPointClosingTransactionLineItemDefinition::TYPE_TRINKGELDAN,
        CashPointClosingTransactionLineItemDefinition::TYPE_RABATT,
        CashPointClosingTransactionLineItemDefinition::TYPE_AUFSCHLAG,
        CashPointClosingTransactionLineItemDefinition::TYPE_ZUSCHUSSECHT,
        CashPointClosingTransactionLineItemDefinition::TYPE_ZUSCHUSSUNECHT,
    ];
    public const LINE_ITEM_GROUP_CASH_MOVEMENT_TYPES = [
        CashPointClosingTransactionLineItemDefinition::TYPE_ANFANGSBESTAND,
        CashPointClosingTransactionLineItemDefinition::TYPE_PRIVATEINLAGE,
        CashPointClosingTransactionLineItemDefinition::TYPE_PRIVATENTNAHME,
        CashPointClosingTransactionLineItemDefinition::TYPE_GELDTRANSIT,
        CashPointClosingTransactionLineItemDefinition::TYPE_LOHNZAHLUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_AUSZAHLUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_EINZAHLUNG,
        CashPointClosingTransactionLineItemDefinition::TYPE_DIFFERENZSOLLIST,
    ];

    public string $lineItemType;
    public string $paymentType;
    public float $taxRate;
    public Price $total;

    public function jsonSerialize(): array
    {
        return [
            'lineItemType' => $this->lineItemType,
            'paymentType' => $this->paymentType,
            'taxRate' => $this->taxRate,
            'total' => $this->total,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->lineItemType = $array['lineItemType'];
        $self->paymentType = $array['paymentType'];
        $self->taxRate = (float) $array['taxRate'];
        $self->total = Price::fromArray($array['total']);

        return $self;
    }

    public function getLineItemTypeGroup(): string
    {
        if (in_array($this->lineItemType, self::LINE_ITEM_GROUP_SALES_TYPES)) {
            return self::LINE_ITEM_GROUP_SALES;
        }
        if (in_array($this->lineItemType, self::LINE_ITEM_GROUP_CASH_MOVEMENT_TYPES)) {
            return self::LINE_ITEM_GROUP_CASH_MOVEMENTS;
        }

        throw new Exception(
            sprintf('Line item type %s could not be matched to any line item type group.', $this->lineItemType),
        );
    }
}
