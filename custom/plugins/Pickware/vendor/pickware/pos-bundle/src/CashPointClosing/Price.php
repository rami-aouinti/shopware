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

class Price implements JsonSerializable
{
    private float $exclVat;
    private float $inclVat;
    private float $vat;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'exclVat' => $this->exclVat,
            'inclVat' => $this->inclVat,
            'vat' => $this->vat,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->exclVat = $array['exclVat'];
        $self->inclVat = $array['inclVat'];
        $self->vat = $array['vat'];

        return $self;
    }

    public static function createEmpty(): self
    {
        $self = new self();

        $self->exclVat = 0.0;
        $self->inclVat = 0.0;
        $self->vat = 0.0;

        return $self;
    }

    public function add(Price $price): void
    {
        $this->exclVat = round($this->exclVat + $price->getExclVat(), 2);
        $this->inclVat = round($this->inclVat + $price->getInclVat(), 2);
        $this->vat = round($this->vat + $price->getVat(), 2);
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
