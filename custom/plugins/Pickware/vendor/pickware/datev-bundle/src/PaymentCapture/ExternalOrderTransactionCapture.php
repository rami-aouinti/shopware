<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture;

use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ExternalOrderTransactionCapture
{
    public function __construct(
        private readonly string $orderId,
        private readonly float $amount,
        private readonly DateTimeInterface $processedAt,
        private readonly string $transactionReference,
    ) {}

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getProcessedAt(): DateTimeInterface
    {
        return $this->processedAt;
    }

    public function getTransactionReference(): string
    {
        return $this->transactionReference;
    }
}
