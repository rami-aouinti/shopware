<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\OidcClientBundle\Client;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class OpenIdConnectClientConfiguration
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        private string $redirectUri,
        private string $wellKnownConfigurationEndpoint,
        private array $scopes,
        private string $clientId,
        private string $clientSecret,
    ) {}

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function getWellKnownConfigurationEndpoint(): string
    {
        return $this->wellKnownConfigurationEndpoint;
    }

    /**
     * @return string[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }
}
