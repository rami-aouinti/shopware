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

use Pickware\DatevBundle\Config\DatevCustomerGroupInformationCustomFieldSet;
use Pickware\DatevBundle\Config\DatevCustomerInformationCustomFieldSet;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class SpecificCompanyCodes
{
    private function __construct(
        private readonly ?string $customerSpecificCompanyCode,
        private readonly ?string $customerGroupSpecificCompanyCode,
    ) {}

    public static function createFromCustomFields(?array $customerCustomFields, ?array $customerGroupCustomFields)
    {
        return new self(
            customerSpecificCompanyCode: $customerCustomFields[DatevCustomerInformationCustomFieldSet::CUSTOM_FIELD_NAME_CUSTOMER_SPECIFIC_COMPANY_CODE] ?? null,
            customerGroupSpecificCompanyCode: $customerGroupCustomFields[DatevCustomerGroupInformationCustomFieldSet::CUSTOM_FIELD_NAME_CUSTOMER_GROUP_SPECIFIC_COMPANY_CODE] ?? null,
        );
    }

    public function getCustomerSpecificCompanyCode(): ?string
    {
        return $this->customerSpecificCompanyCode;
    }

    public function getCustomerGroupSpecificCompanyCode(): ?string
    {
        return $this->customerGroupSpecificCompanyCode;
    }
}
