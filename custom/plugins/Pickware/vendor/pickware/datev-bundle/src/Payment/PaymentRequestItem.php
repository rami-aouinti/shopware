<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Payment;

use Pickware\DatevBundle\Config\AccountAssignment\Item\ClearingAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\DebtorAccountRequestItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PaymentRequestItem
{
    public function __construct(
        private readonly float $amount,
        private readonly OrderEntity $order,
        private readonly string $paymentCaptureId,
        private readonly ClearingAccountRequestItem $accountRequestItem,
        private readonly DebtorAccountRequestItem $contraAccountRequestItem,
    ) {}

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getPaymentCaptureId(): string
    {
        return $this->paymentCaptureId;
    }

    public function getAccountRequestItem(): ClearingAccountRequestItem
    {
        return $this->accountRequestItem;
    }

    public function getContraAccountRequestItem(): DebtorAccountRequestItem
    {
        return $this->contraAccountRequestItem;
    }
}
