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

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountDetermination
{
    private function __construct(
        private readonly ?int $account,
        private ?int $companyCode,
        private readonly AccountDeterminationType $type,
    ) {}

    public static function createForStaticAccount(?int $account): self
    {
        return new self(
            account: $account,
            companyCode: null,
            type: AccountDeterminationType::Static,
        );
    }

    public static function createForIndividualDebtorAccount(?int $account): self
    {
        return new self(
            account: $account,
            companyCode: null,
            type: AccountDeterminationType::IndividualDebtor,
        );
    }

    public static function createForCustomerSpecificDebtorAccount(?int $account): self
    {
        return new self(
            account: $account,
            companyCode: null,
            type: AccountDeterminationType::CustomerSpecificDebtor,
        );
    }

    public function getAccount(): ?int
    {
        if ($this->account === null) {
            return null;
        }

        if ($this->companyCode === null) {
            return $this->account;
        }

        return $this->account * 100 + $this->companyCode;
    }

    /**
     * @param int $companyCode The company code to add to the account number. Must have two digits assuming a leading 0
     * for single digit codes, therefore be between 0 and 99.
     */
    public function addCompanyCode(int $companyCode): void
    {
        if ($companyCode < 0 || $companyCode > 99) {
            throw new InvalidArgumentException('The company code must be between 0 and 99.');
        }

        $this->companyCode = $companyCode;
    }

    public function getType(): AccountDeterminationType
    {
        return $this->type;
    }
}
