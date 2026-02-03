<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DhlExpressApiClientConfig
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly bool $shouldUseTestingEndpoint = false,
    ) {}

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function shouldUseTestingEndpoint(): bool
    {
        return $this->shouldUseTestingEndpoint;
    }
}
