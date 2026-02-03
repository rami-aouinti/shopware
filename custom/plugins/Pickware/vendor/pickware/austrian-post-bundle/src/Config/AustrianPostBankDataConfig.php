<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Config;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostBankDataConfig
{
    public function __construct(
        private readonly string $accountOwnerName,
        private readonly string $iban,
        private readonly string $bic,
    ) {
        if (str_contains($this->accountOwnerName, '|')) {
            throw new InvalidArgumentException('The account owner name is not allowed to contain "|".');
        }
        if (str_contains($this->iban, '|')) {
            throw new InvalidArgumentException('The IBAN is not allowed to contain "|".');
        }
        if (str_contains($this->bic, '|')) {
            throw new InvalidArgumentException('The BIC is not allowed to contain "|".');
        }
    }

    public function getAccountInformationString(): string
    {
        return sprintf('%s | %s | %s', $this->iban, $this->bic, $this->accountOwnerName);
    }
}
