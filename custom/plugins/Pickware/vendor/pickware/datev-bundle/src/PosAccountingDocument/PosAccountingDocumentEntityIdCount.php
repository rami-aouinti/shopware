<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosAccountingDocument;

use JsonSerializable;
use Pickware\DatevBundle\EntryBatch\EntityIdCount;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosAccountingDocumentEntityIdCount implements EntityIdCount, JsonSerializable
{
    public function __construct(
        private readonly int $orderCount,
        private readonly int $returnOrderCount,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            orderCount: $payload['orderCount'],
            returnOrderCount: $payload['returnOrderCount'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'orderCount' => $this->orderCount,
            'returnOrderCount' => $this->returnOrderCount,
        ];
    }

    public function getOrderCount(): int
    {
        return $this->orderCount;
    }

    public function getReturnOrderCount(): int
    {
        return $this->returnOrderCount;
    }

    public function getTotal(): int
    {
        return $this->orderCount + $this->returnOrderCount;
    }
}
