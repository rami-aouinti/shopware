<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment\Conditions;

use Pickware\DatevBundle\Config\AccountAssignment\Item\ClearingAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\DebtorAccountRequestItem;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PaymentMethodCondition implements ClearingAccountCondition, DebtorAccountCondition
{
    public function __construct(private readonly string $paymentMethodId) {}

    /**
     * @param ClearingAccountRequestItem|DebtorAccountRequestItem $item
     */
    public function matches($item): bool
    {
        return $this->paymentMethodId === $item->getPaymentMethodId();
    }
}
