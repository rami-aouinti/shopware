<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Authentication;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class Token implements JsonSerializable
{
    /**
     * @param DateTimeInterface $validUntil The token is only valid before this time, meaning the millisecond
     *     represented by this time is not included in the validity period.
     *     Example: If the token is valid until 2021-01-01T00:00:00Z, it is valid for 2020-12-31T23:59:59.999Z but not
     *     for 2021-01-01T00:00:00Z.
     */
    public function __construct(
        private readonly string $stringRepresentation,
        private readonly DateTimeInterface $creationTime,
        private readonly DateTimeInterface $validUntil,
    ) {}

    public function getStringRepresentation(): string
    {
        return $this->stringRepresentation;
    }

    public function isValidAtTime(DateTimeInterface $time): bool
    {
        // Add a buffer to the validity period to account for clock skew.
        $buffer = new DateInterval('PT30S');

        return $time < (new DateTimeImmutable($this->validUntil->format('c')))->sub($buffer);
    }

    public function jsonSerialize(): array
    {
        return [
            'stringRepresentation' => $this->stringRepresentation,
            'creationTime' => $this->creationTime->format('c'),
            'validUntil' => $this->validUntil->format('c'),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['stringRepresentation'],
            new DateTimeImmutable($data['creationTime']),
            new DateTimeImmutable($data['validUntil']),
        );
    }
}
