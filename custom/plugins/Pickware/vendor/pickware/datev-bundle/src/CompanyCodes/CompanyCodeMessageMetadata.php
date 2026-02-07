<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\CompanyCodes;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CompanyCodeMessageMetadata
{
    public function __construct(
        private readonly string $customerNumber,
        private readonly string $customerGroupName,
        private readonly string $salesChannelName,
        private readonly string $documentType,
        private readonly string $documentNumber,
        private readonly string $orderNumber,
    ) {}

    public function getCustomerNumber(): string
    {
        return $this->customerNumber;
    }

    public function getCustomerGroupName(): string
    {
        return $this->customerGroupName;
    }

    public function getSalesChannelName(): string
    {
        return $this->salesChannelName;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }
}
