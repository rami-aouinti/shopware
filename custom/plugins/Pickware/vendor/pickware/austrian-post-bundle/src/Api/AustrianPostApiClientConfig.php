<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostApiClientConfig
{
    public function __construct(
        private readonly int $clientId,
        private readonly int $orgUnitId,
        private readonly string $orgUnitGuid,
        private readonly bool $shouldUseTestingEndpoint,
    ) {}

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getOrgUnitId(): int
    {
        return $this->orgUnitId;
    }

    public function getOrgUnitGuid(): string
    {
        return $this->orgUnitGuid;
    }

    public function shouldUseTestingEndpoint(): bool
    {
        return $this->shouldUseTestingEndpoint;
    }
}
