<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\OidcClient;

use Pickware\OidcClientBundle\Client\OpenIdConnectClientConfiguration;
use Pickware\OidcClientBundle\Client\OpenIdConnectClientConfigurationFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BusinessPlatformOpenIdConnectClientConfigurationFactory implements OpenIdConnectClientConfigurationFactoryInterface
{
    public const OPEN_ID_CONNECT_CLIENT_ID_PREFIX = 'shopware_6_plugin_';

    private string $clientId;
    private string $redirectUri;

    public function __construct(
        #[Autowire(param: 'pickware_license.oidc.client_secret')]
        private readonly string $clientSecret,
        #[Autowire(param: 'pickware_license.business_platform_base_url')]
        private readonly string $businessPlatformBaseUrl,
        #[Autowire(env: 'APP_URL')]
        string $shopUrl,
    ) {
        $normalizedShopUrl = rtrim($shopUrl, '/\\');
        $this->clientId = sprintf('%s%s', self::OPEN_ID_CONNECT_CLIENT_ID_PREFIX, $normalizedShopUrl);
        $this->redirectUri = sprintf('%s/api/_action/pickware-oidc/callback', $normalizedShopUrl);
    }

    public function __invoke(): OpenIdConnectClientConfiguration
    {
        return new OpenIdConnectClientConfiguration(
            redirectUri: $this->redirectUri,
            wellKnownConfigurationEndpoint: "{$this->businessPlatformBaseUrl}/oidc/.well-known/openid-configuration",
            scopes: [
                BusinessPlatformOpenIdConnectScopes::OpenId->value,
                BusinessPlatformOpenIdConnectScopes::License->value,
            ],
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
        );
    }
}
