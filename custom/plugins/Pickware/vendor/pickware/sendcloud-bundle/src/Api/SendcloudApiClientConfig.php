<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Api;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class SendcloudApiClientConfig
{
    public function __construct(
        private readonly string $publicKey,
        private readonly string $secretKey,
    ) {}

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }
}
