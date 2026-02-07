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
class CashMovementAccounts implements JsonSerializable
{
    public function __construct(
        private readonly ?int $account,
        private readonly ?int $contraAccount,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'account' => $this->getAccount(),
            'contraAccount' => $this->getContraAccount(),
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            account: $array['account'] ?? null,
            contraAccount: $array['contraAccount'] ?? null,
        );
    }

    public function getAccount(): ?int
    {
        return $this->account;
    }

    public function getContraAccount(): ?int
    {
        return $this->contraAccount;
    }
}
