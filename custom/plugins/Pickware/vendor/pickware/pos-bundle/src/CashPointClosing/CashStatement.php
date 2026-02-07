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

class CashStatement implements JsonSerializable
{
    /**
     * @var CashStatementBusinessCase[]
     */
    private array $businessCases;

    private CashStatementPayment $payment;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'businessCases' => $this->businessCases,
            'payment' => $this->payment,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->businessCases = array_map(fn(array $payload) => CashStatementBusinessCase::fromArray($payload), $array['businessCases']);
        $self->payment = CashStatementPayment::fromArray($array['payment']);

        return $self;
    }

    /**
     * @return CashStatementBusinessCase[]
     */
    public function getBusinessCases(): array
    {
        return $this->businessCases;
    }

    /**
     * @param CashStatementBusinessCase[] $businessCases
     */
    public function setBusinessCases(array $businessCases): void
    {
        $this->businessCases = $businessCases;
        $this->sortBusinessCases();
    }

    public function addBusinessCase(CashStatementBusinessCase $businessCase): void
    {
        $this->businessCases[] = $businessCase;
        $this->sortBusinessCases();
    }

    private function sortBusinessCases(): void
    {
        usort(
            $this->businessCases,
            fn(CashStatementBusinessCase $businessCase1, CashStatementBusinessCase $businessCase2) => strcmp($businessCase1->getType(), $businessCase2->getType()),
        );
    }

    public function getPayment(): CashStatementPayment
    {
        return $this->payment;
    }

    public function setPayment(CashStatementPayment $payment): void
    {
        $this->payment = $payment;
    }
}
