<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosPayment;

use JsonSerializable;
use Pickware\DatevBundle\EntryBatch\EntityIdCount;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosPaymentEntityIdCount implements EntityIdCount, JsonSerializable
{
    public function __construct(
        private readonly int $paymentCaptureCount,
        private readonly int $cashMovementCount,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            paymentCaptureCount: $payload['paymentCaptureCount'],
            cashMovementCount: $payload['cashMovementCount'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'paymentCaptureCount' => $this->paymentCaptureCount,
            'cashMovementCount' => $this->cashMovementCount,
        ];
    }

    public function getPaymentCaptureCount(): int
    {
        return $this->paymentCaptureCount;
    }

    public function getCashMovementCount(): int
    {
        return $this->cashMovementCount;
    }

    public function getTotal(): int
    {
        return $this->paymentCaptureCount + $this->cashMovementCount;
    }
}
