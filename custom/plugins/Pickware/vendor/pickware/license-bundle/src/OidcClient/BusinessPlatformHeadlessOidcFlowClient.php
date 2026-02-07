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

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use League\Uri\Http;
use Pickware\OidcClientBundle\Client\OpenIdConnectClientConfiguration;
use Pickware\OidcClientBundle\Provider\OpenIdConnectDiscoveryProvider;
use Pickware\OidcClientBundle\Provider\OpenIdConnectDiscoveryProviderConfigurationFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Client for performing a headless (non-browser) OIDC flow with the Pickware Business Platform.
 */
class BusinessPlatformHeadlessOidcFlowClient
{
    private readonly OpenIdConnectDiscoveryProvider $provider;
    private readonly Client $httpClient;

    public function __construct(
        #[Autowire(service: 'pickware_license.oidc.business_platform_client_configuration')]
        private readonly OpenIdConnectClientConfiguration $oidcClientConfiguration,
        private readonly OpenIdConnectDiscoveryProviderConfigurationFactory $providerConfigurationFactory,
        #[Autowire(param: 'pickware_license.business_platform_base_url')]
        private readonly string $businessPlatformBaseUrl,
    ) {
        $this->provider = new OpenIdConnectDiscoveryProvider(
            options: $this->providerConfigurationFactory->getDiscoveryProviderOptions($this->oidcClientConfiguration),
            collaborators: $this->providerConfigurationFactory->getCollaborators(),
        );

        $this->httpClient = new Client([
            'base_uri' => $this->businessPlatformBaseUrl,
            'cookies' => new CookieJar(),
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS => false,
        ]);
    }

    /**
     * Performs the complete headless OIDC flow and returns the OIDC access token.
     *
     * @param string $businessPlatformAccessToken Access token obtained from BusinessPlatformAuthenticationClient
     * @param string $installationUuid The plugin installation UUID to include in the state
     *
     * @throws BusinessPlatformHeadlessOidcFlowException
     */
    public function obtainAccessToken(string $businessPlatformAccessToken, string $installationUuid): string
    {
        // Generate authorization URL with PKCE (this also generates the code verifier internally)
        $state = sprintf('%s-%s', $this->provider->getRandomState(), $installationUuid);
        $authorizationUrl = $this->provider->getAuthorizationUrl(['state' => $state]);
        $pkceCodeVerifier = $this->provider->getPkceCode();

        // Start the authorization flow
        $loginInteractionId = $this->startOidcAuthorization($authorizationUrl);

        // Confirm login interaction (Business Platform specific, mimics the SPA behavior)
        $scopeConfirmationInteractionId = $this->confirmLoginInteraction(
            $businessPlatformAccessToken,
            $loginInteractionId,
        );

        // Confirm scopes (Business Platform specific)
        $authorizationCode = $this->confirmScopes(
            $businessPlatformAccessToken,
            $scopeConfirmationInteractionId,
        );

        // Exchange authorization code for access token using the provider
        $accessToken = $this->provider->getAccessToken('authorization_code', [
            'code' => $authorizationCode,
            'code_verifier' => $pkceCodeVerifier,
        ]);

        return $accessToken->getToken();
    }

    /**
     * Starts the OIDC authorization flow by calling the authorization URL and extracting the interaction ID.
     *
     * @throws BusinessPlatformHeadlessOidcFlowException
     */
    private function startOidcAuthorization(string $authorizationUrl): string
    {
        // Extract path and query from authorization URL for the request
        $uri = Http::new($authorizationUrl);
        $query = $uri->getQuery();
        $requestPath = $query ? sprintf('%s?%s', $uri->getPath(), $query) : $uri->getPath();

        $response = $this->httpClient->get($requestPath);

        if ($response->getStatusCode() !== 303) {
            throw BusinessPlatformHeadlessOidcFlowException::unexpectedStatusCode(
                step: 'authorization start',
                expected: 303,
                actual: $response->getStatusCode(),
            );
        }

        $locationUri = Http::new($response->getHeaderLine('Location'));
        $interactionId = basename($locationUri->getPath());

        // Follow to the interaction endpoint
        $interactionResponse = $this->httpClient->get($locationUri->getPath());
        if ($interactionResponse->getStatusCode() !== 302) {
            throw BusinessPlatformHeadlessOidcFlowException::unexpectedStatusCode(
                step: 'interaction redirect',
                expected: 302,
                actual: $interactionResponse->getStatusCode(),
            );
        }

        return $interactionId;
    }

    /**
     * Confirms the Business Platform specific login interaction (mimics the SPA behavior)
     *
     * @throws BusinessPlatformHeadlessOidcFlowException
     */
    private function confirmLoginInteraction(string $accessToken, string $loginInteractionId): string
    {
        // This is what the Pickware Account SPA does when the user logs in or is already logged in.
        $confirmResponse = $this->httpClient->get(
            sprintf('/api/v4/oidc/interaction/%s/confirm', $loginInteractionId),
            [
                RequestOptions::HEADERS => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                ],
            ],
        );
        if ($confirmResponse->getStatusCode() !== 303) {
            throw BusinessPlatformHeadlessOidcFlowException::unexpectedStatusCode(
                step: 'login confirmation',
                expected: 303,
                actual: $confirmResponse->getStatusCode(),
            );
        }

        // Follow to /oidc/auth/{interactionId} (since this is a headless flow)
        $authRedirectUri = Http::new($confirmResponse->getHeaderLine('Location'));
        $authRedirectResponse = $this->httpClient->get(
            $authRedirectUri->getPath(),
            [
                RequestOptions::HEADERS => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                ],
            ],
        );
        if ($authRedirectResponse->getStatusCode() !== 303) {
            throw BusinessPlatformHeadlessOidcFlowException::unexpectedStatusCode(
                step: 'auth redirect',
                expected: 303,
                actual: $authRedirectResponse->getStatusCode(),
            );
        }

        // Follow to the scope confirmation interaction
        $scopeInteractionUri = Http::new($authRedirectResponse->getHeaderLine('Location'));
        $scopeInteractionResponse = $this->httpClient->get(
            $scopeInteractionUri->getPath(),
            [
                RequestOptions::HEADERS => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                ],
            ],
        );
        if ($scopeInteractionResponse->getStatusCode() !== 302) {
            throw BusinessPlatformHeadlessOidcFlowException::unexpectedStatusCode(
                step: 'scope interaction',
                expected: 302,
                actual: $scopeInteractionResponse->getStatusCode(),
            );
        }

        $scopeConfirmationInteractionId = basename($scopeInteractionUri->getPath());

        return $scopeConfirmationInteractionId;
    }

    /**
     * Confirms the scopes and extracts the authorization code.
     *
     * @throws BusinessPlatformHeadlessOidcFlowException
     */
    private function confirmScopes(string $accessToken, string $scopeConfirmationInteractionId): string
    {
        // Skip ahead of any consent collection step and directly confirm the scopes.
        $confirmScopesResponse = $this->httpClient->get(
            sprintf('/api/v4/oidc/interaction/%s/confirm-scopes', $scopeConfirmationInteractionId),
            [
                RequestOptions::HEADERS => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                ],
            ],
        );
        if ($confirmScopesResponse->getStatusCode() !== 303) {
            throw BusinessPlatformHeadlessOidcFlowException::unexpectedStatusCode(
                step: 'scope confirmation',
                expected: 303,
                actual: $confirmScopesResponse->getStatusCode(),
            );
        }

        // Follow to get the authorization code
        $codeRedirectUri = Http::new($confirmScopesResponse->getHeaderLine('Location'));
        $codeRedirectResponse = $this->httpClient->get($codeRedirectUri->getPath());
        if ($codeRedirectResponse->getStatusCode() !== 303) {
            throw BusinessPlatformHeadlessOidcFlowException::unexpectedStatusCode(
                step: 'code redirect',
                expected: 303,
                actual: $codeRedirectResponse->getStatusCode(),
            );
        }

        $finalUri = Http::new($codeRedirectResponse->getHeaderLine('Location'));
        $queryParams = [];
        parse_str($finalUri->getQuery(), $queryParams);

        if (!isset($queryParams['code'])) {
            throw BusinessPlatformHeadlessOidcFlowException::missingAuthorizationCode(redirectUrl: $finalUri->jsonSerialize());
        }

        return $queryParams['code'];
    }
}
