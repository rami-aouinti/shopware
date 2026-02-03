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

use Pickware\OidcClientBundle\Provider\OpenIdConnectDiscoveryProvider;
use Pickware\OidcClientBundle\Provider\OpenIdConnectDiscoveryProviderConfigurationFactory;
use Symfony\Component\HttpFoundation\RequestStack;

class Oauth2PkceClientFactory
{
    public function __construct(
        private readonly OpenIdConnectDiscoveryProviderConfigurationFactory $openIdConnectDiscoveryProviderConfigurationFactory,
        private readonly RequestStack $requestStack,
    ) {}

    public function __invoke(
        OpenIdConnectClientConfiguration $openIdConnectClientConfiguration,
    ): Oauth2PkceClient {
        $provider = new OpenIdConnectDiscoveryProvider(
            options: $this->openIdConnectDiscoveryProviderConfigurationFactory->getDiscoveryProviderOptions(
                $openIdConnectClientConfiguration,
            ),
            collaborators: $this->openIdConnectDiscoveryProviderConfigurationFactory->getCollaborators(),
        );

        return new Oauth2PkceClient($provider, $this->requestStack);
    }
}
