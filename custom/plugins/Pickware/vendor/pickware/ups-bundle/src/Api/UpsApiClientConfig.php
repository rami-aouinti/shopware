<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class UpsApiClientConfig
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly bool $shouldUseTestingEndpoint = false,
    ) {}

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function shouldUseTestingEndpoint(): bool
    {
        return $this->shouldUseTestingEndpoint;
    }
}
