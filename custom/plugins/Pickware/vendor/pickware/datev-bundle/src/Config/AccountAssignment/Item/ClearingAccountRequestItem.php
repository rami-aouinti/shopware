<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment\Item;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ClearingAccountRequestItem implements AccountRequestItem
{
    public function __construct(
        private readonly string $key,
        private readonly string $paymentMethodId,
    ) {}

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
