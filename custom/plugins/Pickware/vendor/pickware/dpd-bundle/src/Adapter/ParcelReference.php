<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Adapter;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ParcelReference
{
    public function __construct(
        private readonly string $shipmentId,
        private readonly int $index,
    ) {}

    public function getShipmentId(): string
    {
        return $this->shipmentId;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function toString(): string
    {
        return sprintf('%s_%d', $this->shipmentId, $this->index);
    }

    public static function fromString(string $sequenceNumber): self
    {
        [
            $shipmentId,
            $parcelIndex,
        ] = explode('_', $sequenceNumber);

        return new self(
            $shipmentId,
            (int) $parcelIndex,
        );
    }
}
