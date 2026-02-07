<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelPacking;

use InvalidArgumentException;
use JsonSerializable;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\UnitsOfMeasurement\Dimensions\BoxDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;

class ParcelPackingConfiguration implements JsonSerializable
{
    private Weight $fillerWeightAbsoluteSurchargePerParcel;
    private float $fillerWeightRelativeSurchargePerParcel;
    private ?Weight $fallbackParcelWeight;
    private ?Weight $maxParcelWeight;
    private ?BoxDimensions $defaultBoxDimensions;

    public function __construct(
        ?Weight $fillerWeightAbsoluteSurchargePerParcel = null,
        ?Weight $fallbackParcelWeight = null,
        ?Weight $maxParcelWeight = null,
        ?BoxDimensions $defaultBoxDimensions = null,
        ?float $fillerWeightRelativeSurchargePerParcel = 0.0,
        // Should not be used as a parameter, but as a fallback for $fillerWeightAbsoluteSurchargePerParcel for backwards compatibility.
        // Can be removed in the next major version.
        ?Weight $fillerWeightPerParcel = null,
    ) {
        if (isset($fillerWeightPerParcel)) {
            trigger_error(
                'The parameter $fillerWeightPerParcel is deprecated and will be removed in the next major version. ' .
                'Please use $fillerWeightAbsoluteSurchargePerParcel instead.',
                E_USER_DEPRECATED,
            );
        }

        if (isset($fillerWeightPerParcel) && isset($fillerWeightAbsoluteSurchargePerParcel)) {
            throw new InvalidArgumentException(
                'You must not provide both $fillerWeightPerParcel and $fillerWeightAbsoluteSurchargePerParcel.',
            );
        }

        $absoluteFillerWeightPerParcel = $fillerWeightAbsoluteSurchargePerParcel ?? $fillerWeightPerParcel;

        $this->fillerWeightAbsoluteSurchargePerParcel = $absoluteFillerWeightPerParcel ?? new Weight(0, 'kg');
        $this->fallbackParcelWeight = $fallbackParcelWeight;
        $this->maxParcelWeight = $maxParcelWeight;
        $this->defaultBoxDimensions = $defaultBoxDimensions;
        $this->fillerWeightRelativeSurchargePerParcel = $fillerWeightRelativeSurchargePerParcel ?? 0.0;
    }

    public function jsonSerialize(): array
    {
        return [
            'fillerWeightAbsoluteSurchargePerParcel' => $this->fillerWeightAbsoluteSurchargePerParcel,
            'fillerWeightRelativeSurchargePerParcel' => $this->fillerWeightRelativeSurchargePerParcel,
            'fallbackParcelWeight' => $this->fallbackParcelWeight,
            'maxParcelWeight' => $this->maxParcelWeight,
            'defaultBoxDimensions' => $this->defaultBoxDimensions,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            doIf($array['fillerWeightAbsoluteSurchargePerParcel'], Weight::fromArray(...)),
            doIf($array['fallbackParcelWeight'], Weight::fromArray(...)),
            doIf($array['maxParcelWeight'], Weight::fromArray(...)),
            doIf($array['defaultBoxDimensions'], BoxDimensions::fromArray(...)),
            doIf($array['fillerWeightRelativeSurchargePerParcel'], fn($value) => (float) $value),
        );
    }

    public static function createDefault(): self
    {
        return new self();
    }

    /**
     * Creates a copy
     */
    public function createCopy(): self
    {
        $self = new self();
        $self->fillerWeightAbsoluteSurchargePerParcel = $this->fillerWeightAbsoluteSurchargePerParcel;
        $self->fillerWeightRelativeSurchargePerParcel = $this->fillerWeightRelativeSurchargePerParcel;
        $self->fallbackParcelWeight = $this->fallbackParcelWeight;
        $self->maxParcelWeight = $this->maxParcelWeight;
        $self->defaultBoxDimensions = $this->defaultBoxDimensions;

        return $self;
    }

    public function getFillerWeightAbsoluteSurchargePerParcel(): Weight
    {
        return $this->fillerWeightAbsoluteSurchargePerParcel;
    }

    public function getFillerWeightRelativeSurchargePerParcel(): float
    {
        return $this->fillerWeightRelativeSurchargePerParcel;
    }

    public function getFallbackParcelWeight(): ?Weight
    {
        return $this->fallbackParcelWeight;
    }

    public function getMaxParcelWeight(): ?Weight
    {
        return $this->maxParcelWeight;
    }

    public function getDefaultBoxDimensions(): ?BoxDimensions
    {
        return $this->defaultBoxDimensions;
    }

    public function setMaxParcelWeight(?Weight $maxParcelWeight): void
    {
        $this->maxParcelWeight = $maxParcelWeight;
    }
}
