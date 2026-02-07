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

class CashStatementBusinessCaseAmount implements JsonSerializable
{
    private ?float $taxRate;
    private float $inclVat;
    private float $exclVat;
    private float $vat;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'taxRate' => $this->taxRate,
            'inclVat' => $this->inclVat,
            'exclVat' => $this->exclVat,
            'vat' => $this->vat,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->taxRate = $array['taxRate'];
        $self->inclVat = $array['inclVat'];
        $self->exclVat = $array['exclVat'];
        $self->vat = $array['vat'];

        return $self;
    }

    public function getTaxRate(): ?float
    {
        return $this->taxRate;
    }

    public function setTaxRate(?float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getInclVat(): float
    {
        return $this->inclVat;
    }

    public function setInclVat(float $inclVat): void
    {
        $this->inclVat = $inclVat;
    }

    public function getExclVat(): float
    {
        return $this->exclVat;
    }

    public function setExclVat(float $exclVat): void
    {
        $this->exclVat = $exclVat;
    }

    public function getVat(): float
    {
        return $this->vat;
    }

    public function setVat(float $vat): void
    {
        $this->vat = $vat;
    }
}
