<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Privacy;

use JsonSerializable;

class PrivacyConfiguration implements JsonSerializable
{
    private readonly DataTransferPolicy $emailTransferPolicy;
    private readonly bool $isPhoneTransferAllowed;

    public function __construct(
        ?DataTransferPolicy $emailTransferPolicy = null,
        ?bool $isPhoneTransferAllowed = null,
    ) {
        $this->emailTransferPolicy = $emailTransferPolicy ?? DataTransferPolicy::Always;
        $this->isPhoneTransferAllowed = $isPhoneTransferAllowed ?? true;
    }

    public static function fromArray(array $array): self
    {
        return new self(
            isset($array['emailTransferPolicy']) ? DataTransferPolicy::from($array['emailTransferPolicy']) : null,
            $array['isPhoneTransferAllowed'] ?? null,
        );
    }

    public function getEmailTransferPolicy(): DataTransferPolicy
    {
        return $this->emailTransferPolicy;
    }

    public function isPhoneTransferAllowed(): bool
    {
        return $this->isPhoneTransferAllowed;
    }

    public function jsonSerialize(): array
    {
        return [
            'emailTransferPolicy' => $this->emailTransferPolicy,
            'isPhoneTransferAllowed' => $this->isPhoneTransferAllowed,
        ];
    }
}
