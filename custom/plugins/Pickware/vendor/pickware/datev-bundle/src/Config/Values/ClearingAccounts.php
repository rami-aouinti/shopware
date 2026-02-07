<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\Values;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ClearingAccounts implements JsonSerializable
{
    public function __construct(
        /**
         * @var array<string, int>
         */
        private readonly array $accountsByPaymentMethodId,
        private readonly ?int $defaultAccount,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'defaultAccount' => $this->getDefaultAccount(),
            'accountsByPaymentMethodId' => $this->getAccountsByPaymentMethodId(),
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            accountsByPaymentMethodId: $array['accountsByPaymentMethodId'] ?? [],
            defaultAccount: $array['defaultAccount'] ?? null,
        );
    }

    /**
     * @return array<string, int>
     */
    public function getAccountsByPaymentMethodId(): array
    {
        return $this->accountsByPaymentMethodId;
    }

    public function getDefaultAccount(): ?int
    {
        return $this->defaultAccount;
    }
}
