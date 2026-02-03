<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\OidcClientBundle\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class OpenIdConnectDiscoveryProvider extends GenericProvider
{
    protected OpenIdConnectDiscoveryProviderConfiguration $openIdConnectProviderConfiguration;

    protected function getRequiredOptions(): array
    {
        return [
            'redirectUri',
            'clientSecret',
            'clientId',
            'openIdConnectProviderConfiguration',
        ];
    }

    public function getPkceMethod()
    {
        return AbstractProvider::PKCE_METHOD_S256;
    }

    public function getBaseAuthorizationUrl(): string
    {
        return $this->openIdConnectProviderConfiguration->authorizationEndpoint;
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return $this->openIdConnectProviderConfiguration->tokenEndpoint;
    }

    public function getDefaultScopes(): array
    {
        return $this->openIdConnectProviderConfiguration->scopes;
    }

    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    /**
     * We expose this method because we need to be able to generate random states in the license-bundle but
     * {@link GenericProvider::getRandomState} is protected.
     */
    public function getRandomState($length = 32): string
    {
        return parent::getRandomState($length);
    }
}
