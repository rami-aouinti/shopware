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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosPaymentCaptureDraft
{
    public function __construct(
        private readonly string $salesChannelId,
        private readonly string $stateId,
        private readonly ?string $orderTransactionId,
        private readonly ?string $stateMachineHistoryEntryId,
        private readonly ?string $returnOrderRefundId,
    ) {}

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function getOrderTransactionId(): ?string
    {
        return $this->orderTransactionId;
    }

    public function getStateMachineHistoryEntryId(): ?string
    {
        return $this->stateMachineHistoryEntryId;
    }

    public function getReturnOrderRefundId(): ?string
    {
        return $this->returnOrderRefundId;
    }
}
