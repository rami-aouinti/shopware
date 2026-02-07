<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment;

use Pickware\DatevBundle\Config\DatevCustomerInformationCustomFieldSet;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountAssignmentCustomer
{
    private function __construct(
        private readonly ?string $customerNumber,
        private readonly ?string $customerSpecificDebtorAccount,
    ) {}

    public static function fromRawCustomerData(?string $customerNumber, ?array $customerCustomFields): self
    {
        $customerSpecificDebtorAccount = $customerCustomFields[
            DatevCustomerInformationCustomFieldSet::CUSTOM_FIELD_NAME_CUSTOMER_SPECIFIC_DEBTOR_ACCOUNT
        ] ?? null;

        return new self($customerNumber, $customerSpecificDebtorAccount);
    }

    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    public function getCustomerSpecificDebtorAccount(): ?string
    {
        return $this->customerSpecificDebtorAccount;
    }
}
