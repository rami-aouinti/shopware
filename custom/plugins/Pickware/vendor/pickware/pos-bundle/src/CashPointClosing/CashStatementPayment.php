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

class CashStatementPayment implements JsonSerializable
{
    private float $fullAmount;
    private float $cashAmount;

    /**
     * @var CashStatementPaymentCashAmount[]
     */
    private array $cashAmountsByCurrency;

    /**
     * @var CashStatementPaymentType[]
     */
    private array $paymentTypes;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'fullAmount' => $this->fullAmount,
            'cashAmount' => $this->cashAmount,
            'cashAmountsByCurrency' => $this->cashAmountsByCurrency,
            'paymentTypes' => $this->paymentTypes,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->fullAmount = $array['fullAmount'];
        $self->cashAmount = $array['cashAmount'];
        $self->cashAmountsByCurrency = array_map(fn(array $payload) => CashStatementPaymentCashAmount::fromArray($payload), $array['cashAmountsByCurrency']);
        $self->paymentTypes = array_map(fn(array $payload) => CashStatementPaymentType::fromArray($payload), $array['paymentTypes']);

        return $self;
    }

    public function getFullAmount(): float
    {
        return $this->fullAmount;
    }

    public function setFullAmount(float $fullAmount): void
    {
        $this->fullAmount = $fullAmount;
    }

    public function getCashAmount(): float
    {
        return $this->cashAmount;
    }

    public function setCashAmount(float $cashAmount): void
    {
        $this->cashAmount = $cashAmount;
    }

    /**
     * @return CashStatementPaymentCashAmount[]
     */
    public function getCashAmountsByCurrency(): array
    {
        return $this->cashAmountsByCurrency;
    }

    /**
     * @param CashStatementPaymentCashAmount[] $cashAmountsByCurrency
     */
    public function setCashAmountsByCurrency(array $cashAmountsByCurrency): void
    {
        $this->cashAmountsByCurrency = $cashAmountsByCurrency;
        $this->sortCashAmountyByCurrency();
    }

    public function addCashAmountsByCurrency(CashStatementPaymentCashAmount $cashAmountByCurrency): void
    {
        $this->cashAmountsByCurrency[] = $cashAmountByCurrency;
        $this->sortCashAmountyByCurrency();
    }

    private function sortCashAmountyByCurrency(): void
    {
        usort(
            $this->cashAmountsByCurrency,
            fn(CashStatementPaymentCashAmount $amount1, CashStatementPaymentCashAmount $amount2): int => strcmp($amount1->getCurrencyCode(), $amount2->getCurrencyCode()),
        );
    }

    /**
     * @return CashStatementPaymentType[]
     */
    public function getPaymentTypes(): array
    {
        return $this->paymentTypes;
    }

    /**
     * @param CashStatementPaymentType[] $paymentTypes
     */
    public function setPaymentTypes(array $paymentTypes): void
    {
        $this->paymentTypes = $paymentTypes;
        $this->sortPaymentTypes();
    }

    public function addPaymentType(CashStatementPaymentType $paymentType): void
    {
        $this->paymentTypes[] = $paymentType;
        $this->sortPaymentTypes();
    }

    private function sortPaymentTypes(): void
    {
        usort(
            $this->paymentTypes,
            fn(CashStatementPaymentType $paymentType1, CashStatementPaymentType $paymentType2): int => strcmp($paymentType1->getType(), $paymentType2->getType()),
        );
    }
}
