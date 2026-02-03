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

use League\OAuth2\Client\Token\AccessToken;
use LogicException;
use Pickware\OidcClientBundle\Provider\OpenIdConnectDiscoveryProvider;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Exclude]
class Oauth2PkceClient
{
    public const CODE_VERIFIER_KEY = 'pickware.pkce_code_verifier';
    public const OAUTH2_SESSION_STATE_KEY = 'pickware.oauth2_client_state';

    public function __construct(
        private readonly OpenIdConnectDiscoveryProvider $provider,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * Generates the authentication server URL to which the user should be redirected to start the OIDC flow.
     */
    public function generateAuthorizationUrl(AuthorizationUrlGenerationOptions $options): string
    {
        $url = $this->provider->getAuthorizationUrl($options->asArray());

        // The state is available only after generating the authorization URL
        $this->getSession()->set(self::OAUTH2_SESSION_STATE_KEY, $this->provider->getState());
        $this->getSession()->set(self::CODE_VERIFIER_KEY, $this->provider->getPkceCode());

        return $url;
    }

    public function getRandomState(): string
    {
        return $this->provider->getRandomState();
    }

    /**
     * Requests the access token from the OIDC server using the authorization code and code verifier.
     * The authorization code is obtained via a redirected back from the authorization server after a successful OIDC
     * flow.
     */
    public function getAccessToken(): string
    {
        $codeVerifier = $this->getSession()->get(self::CODE_VERIFIER_KEY);
        if (!$codeVerifier) {
            throw new LogicException('Unable to fetch token from OAuth2 server because there is no PKCE code verifier stored in the session');
        }
        $this->getSession()->remove(self::CODE_VERIFIER_KEY);

        $expectedState = $this->getSession()->get(self::OAUTH2_SESSION_STATE_KEY);
        $actualState = $this->getCurrentRequest()->get('state');
        if (!$actualState || ($actualState !== $expectedState)) {
            throw Oauth2PkceClientException::invalidState();
        }

        $code = $this->getCurrentRequest()->get('code');
        if (!$code) {
            throw Oauth2PkceClientException::missingAuthorizationCode();
        }

        $accessToken = $this->provider->getAccessToken(
            'authorization_code',
            [
                'code' => $code,
                'code_verifier' => $codeVerifier,
            ],
        );

        return $accessToken->getToken();
    }

    public function refreshAccessToken(string $refreshToken): AccessToken
    {
        return $this->provider->getAccessToken(
            'refresh_token',
            ['refresh_token' => $refreshToken],
        );
    }

    private function getCurrentRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new LogicException('There is no "current request", and it is needed to perform this action');
        }

        return $request;
    }

    private function getSession(): SessionInterface
    {
        return $this->getCurrentRequest()->getSession();
    }
}
