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

use Pickware\DatevBundle\Config\AccountAssignment\TaxStatus;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class RevenueAccountRequestItem implements AccountRequestItem
{
    public function __construct(
        private readonly string $key,
        private readonly string $orderNumber,
        private readonly TaxStatus $taxStatus,
        private readonly ?float $taxRate,
        private readonly ?string $countryIsoCode,
        /** @var string[]|null */
        private readonly ?array $customerVatIds,
        private readonly ?string $billedCompany,
    ) {}

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getTaxStatus(): TaxStatus
    {
        return $this->taxStatus;
    }

    public function getTaxRate(): ?float
    {
        return $this->taxRate;
    }

    public function getCountryIsoCode(): ?string
    {
        return $this->countryIsoCode;
    }

    /**
     * @return string[]|null
     */
    public function getCustomerVatIds(): ?array
    {
        return $this->customerVatIds;
    }

    public function hasVatId(): bool
    {
        return $this->customerVatIds !== null && count($this->customerVatIds) > 0;
    }

    public function getBilledCompany(): ?string
    {
        return $this->billedCompany;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
