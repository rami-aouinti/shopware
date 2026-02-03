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

use Exception;
use GuzzleHttp\ClientInterface as HttpClientInterface;
use GuzzleHttp\Exception\ConnectException;
use Pickware\OidcClientBundle\Client\OpenIdConnectClientConfiguration;
use Pickware\PhpStandardLibrary\Json\Json;
use Psr\Log\LoggerInterface;

/**
 * Provides the configuration for {@link OpenIdConnectDiscoveryProvider} to fit the arguments of
 * {@link \League\OAuth2\Client\Provider\GenericProvider::__construct}.
 */
class OpenIdConnectDiscoveryProviderConfigurationFactory
{
    private HttpClientInterface $httpClient;
    private ?array $wellKnownConfigurationResponse = null;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly GuzzleHttpClientFactory $guzzleHttpClientFactory,
        private readonly LoggerInterface $logger,
    ) {
        // We configure a Guzzle HTTP client with retries instead of using a preconfigured Symfony HTTP client (the
        // "retryable.http_client" service), because the league/oauth2-client library we use requires a Guzzle HTTP
        // client.
        $this->httpClient = $this->guzzleHttpClientFactory->createHttpClientWithRetries();
    }

    /**
     * Provides the second argument for the constructor of {@link OpenIdConnectDiscoveryProvider} which
     * is inherited from {@link \League\OAuth2\Client\Provider\AbstractProvider}'s constructor.
     */
    public function getCollaborators(): array
    {
        return ['httpClient' => $this->httpClient];
    }

    /**
     * Provides the first argument for the constructor of {@link OpenIdConnectDiscoveryProvider} which
     * is inherited from {@link \League\OAuth2\Client\Provider\AbstractProvider}'s constructor.
     */
    public function getDiscoveryProviderOptions(
        OpenIdConnectClientConfiguration $openIdConnectClientConfiguration,
    ): array {
        $wellKnownConfiguration = $this->getWellKnownConfigFromAuthorizationServer($openIdConnectClientConfiguration);
        $discoveryProviderOptions['redirectUri'] = $openIdConnectClientConfiguration->getRedirectUri();
        $discoveryProviderOptions['clientId'] = $openIdConnectClientConfiguration->getClientId();
        $discoveryProviderOptions['clientSecret'] = $openIdConnectClientConfiguration->getClientSecret();
        $discoveryProviderOptions['openIdConnectProviderConfiguration'] = new OpenIdConnectDiscoveryProviderConfiguration(
            wellKnownEndpoint: $openIdConnectClientConfiguration->getWellKnownConfigurationEndpoint(),
            authorizationEndpoint: $wellKnownConfiguration['authorization_endpoint'],
            tokenEndpoint: $wellKnownConfiguration['token_endpoint'],
            jwksUri: $wellKnownConfiguration['jwks_uri'],
            issuer: $wellKnownConfiguration['issuer'],
            userInfoEndpoint: $wellKnownConfiguration['userinfo_endpoint'],
            scopes: $openIdConnectClientConfiguration->getScopes(),
        );

        return $discoveryProviderOptions;
    }

    /**
     * Calls the authentication servers well-known configuration endpoint and returns the response as an array.
     *
     * @see {@link https://openid.net/specs/openid-connect-discovery-1_0.html#rfc.section.4}
     */
    private function getWellKnownConfigFromAuthorizationServer(
        OpenIdConnectClientConfiguration $openIdConnectClientConfiguration,
    ): array {
        if ($this->wellKnownConfigurationResponse !== null) {
            return $this->wellKnownConfigurationResponse;
        }
        $wellKnownConfigurationEndpoint = $openIdConnectClientConfiguration->getWellKnownConfigurationEndpoint();

        try {
            $wellKnownConfigurationResponse = $this->httpClient->request('GET', $wellKnownConfigurationEndpoint)
                ->getBody()
                ->getContents();
            $this->wellKnownConfigurationResponse = Json::decodeToArray($wellKnownConfigurationResponse, depth: 10);

            return $this->wellKnownConfigurationResponse;
        } catch (ConnectException $connectException) {
            throw OpenIdConnectDiscoveryProviderConfigurationFactoryException::authenticationServerNotReachable(
                previous: $connectException,
            );
        } catch (Exception $exception) {
            $this->logger->error(
                "Failed to fetch well known configuration from {$wellKnownConfigurationEndpoint}",
                ['exception' => $exception],
            );

            throw $exception;
        }
    }
}
