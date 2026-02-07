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

class CashStatementBusinessCase implements JsonSerializable
{
    /**
     * @var CashStatementBusinessCaseAmount[]
     */
    private array $amountsPerTaxRate;

    private string $type;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'amountsPerTaxRate' => $this->amountsPerTaxRate,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->type = $array['type'];
        $self->amountsPerTaxRate = array_map(fn(array $payload) => CashStatementBusinessCaseAmount::fromArray($payload), $array['amountsPerTaxRate']);

        return $self;
    }

    /**
     * @return CashStatementBusinessCaseAmount[]
     */
    public function getAmountsPerTaxRate(): array
    {
        return $this->amountsPerTaxRate;
    }

    /**
     * @param CashStatementBusinessCaseAmount[] $amountsPerTaxRate
     */
    public function setAmountsPerTaxRate(array $amountsPerTaxRate): void
    {
        $this->amountsPerTaxRate = $amountsPerTaxRate;
        $this->sortAmountsPerTaxRate();
    }

    public function addAmountPerTaxRate(CashStatementBusinessCaseAmount $amountPerTaxRate): void
    {
        $this->amountsPerTaxRate[] = $amountPerTaxRate;
        $this->sortAmountsPerTaxRate();
    }

    private function sortAmountsPerTaxRate(): void
    {
        usort(
            $this->amountsPerTaxRate,
            fn(CashStatementBusinessCaseAmount $amount1, CashStatementBusinessCaseAmount $amount2) => $amount1->getTaxRate() - $amount2->getTaxRate(),
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
