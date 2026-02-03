<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient\Model;

use DateTimeInterface;
use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class UsageReport implements JsonSerializable
{
    public function __construct(
        private string $uuid,
        private int $orderCount,
        private DateTimeInterface $createdAt,
        private ?DateTimeInterface $inclusiveIntervalStart = null,
        private ?DateTimeInterface $exclusiveIntervalEnd = null,
    ) {}

    public function jsonSerialize(): mixed
    {
        $data = [
            'uuid' => $this->uuid,
            'orderCount' => $this->orderCount,
            'createdAt' => $this->createdAt->format(DateTimeInterface::ATOM),
        ];

        if ($this->inclusiveIntervalStart !== null) {
            $data['inclusiveIntervalStart'] = $this->inclusiveIntervalStart->format(DateTimeInterface::ATOM);
        }

        if ($this->exclusiveIntervalEnd !== null) {
            $data['exclusiveIntervalEnd'] = $this->exclusiveIntervalEnd->format(DateTimeInterface::ATOM);
        }

        return $data;
    }
}
