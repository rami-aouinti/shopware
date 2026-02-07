<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\OidcClient\Controller;

use League\Uri\Http;
use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountConnectionResult;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountService;
use Pickware\OidcClientBundle\Client\AuthorizationUrlGenerationOptions;
use Pickware\OidcClientBundle\Client\Oauth2PkceClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class OpenIdConnectClientController
{
    public const REDIRECT_AFTER_LOGIN_SESSION_KEY = 'pickware-license-oidc-redirect-after-login';
    private const STATUS_QUERY_PARAMETER = 'pickware_account_connection_status';

    public function __construct(
        #[Autowire(service: 'pickware_license.oidc.business_platform_client')]
        private readonly Oauth2PkceClient $businessPlatformOpenIdConnectClient,
        private readonly PickwareAccountService $pickwareAccountService,
        private readonly PluginInstallationRepository $pluginInstallationRepository,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route(
        path: '/api/_action/pickware-oidc/generate-authorization-url',
        methods: ['GET'],
    )]
    public function generateAuthorizationUrl(
        #[MapQueryParameter(name: 'redirect_after_login_uri')] ?string $redirectAfterLoginUri,
        Request $request,
        Context $context,
    ): Response {
        if ($redirectAfterLoginUri === null) {
            throw new BadRequestException('The "redirect_after_login_uri" query parameter is missing.');
        }
        $this->setRedirectAfterLoginUri($redirectAfterLoginUri, $request);

        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);
        $state = sprintf(
            '%s-%s',
            $this->businessPlatformOpenIdConnectClient->getRandomState(),
            $pluginInstallation->getInstallationUuid(),
        );
        $authorizationUrlGenerationOptions = new AuthorizationUrlGenerationOptions(state: $state);

        $authorizationUrl = $this->businessPlatformOpenIdConnectClient->generateAuthorizationUrl(
            $authorizationUrlGenerationOptions,
        );

        return new JsonResponse(['authorizationUrl' => $authorizationUrl]);
    }

    private function setRedirectAfterLoginUri(string $redirectAfterLoginUri, Request $request): void
    {
        $currentHost = $request->getHost();
        $currentScheme = $request->getScheme();

        $parsedRedirectAfterLoginUri = Http::new($redirectAfterLoginUri);

        if ($parsedRedirectAfterLoginUri->getHost() !== $currentHost) {
            throw new BadRequestException('The "redirect_after_login_uri" query parameter does not match the current host.');
        }
        if ($parsedRedirectAfterLoginUri->getScheme() !== $currentScheme) {
            throw new BadRequestException('The "redirect_after_login_uri" query parameter does not match the current scheme.');
        }

        $request->getSession()->set(self::REDIRECT_AFTER_LOGIN_SESSION_KEY, $redirectAfterLoginUri);
    }

    #[Route(
        path: '/api/_action/pickware-oidc/callback',
        name: 'pickware_license.oidc.callback',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    public function openIdConnectCallback(
        #[MapQueryParameter(name: 'error')] ?string $openIdConnectFlowError,
        #[MapQueryParameter(name: 'error_description')] ?string $openIdConnectFlowErrorDescription,
        Request $request,
        Context $context,
    ): Response {
        $redirectUri = $request->getSession()->get(self::REDIRECT_AFTER_LOGIN_SESSION_KEY);
        if (!$redirectUri) {
            throw new BadRequestException('The redirect URI is missing in the session.');
        }

        if ($openIdConnectFlowError) {
            // This error occurs when the user explicitly aborts the authorization flow by clicking "cancel" on the
            // authorization page. Hence, we don't want to include any error information in the redirect URL.
            if ($openIdConnectFlowError === 'access_denied') {
                return new RedirectResponse($redirectUri);
            }

            $this->logger->error('Encountered OpenId Connect flow error during OpenID Connect callback.', [
                'error' => $openIdConnectFlowError,
                'error_description' => $openIdConnectFlowErrorDescription,
            ]);

            return $this->generateRedirectResponseWithStatus(
                uri: $redirectUri,
                status: PickwareAccountConnectionStatus::AuthorizationError,
            );
        }

        $accessToken = $this->businessPlatformOpenIdConnectClient->getAccessToken();
        $connectionResult = $this->pickwareAccountService->connectToPickwareAccountViaOidcAccessToken($accessToken, $context);

        if ($connectionResult !== PickwareAccountConnectionResult::Success) {
            return $this->generateRedirectResponseWithStatus(
                uri: $redirectUri,
                status: PickwareAccountConnectionStatus::LicenseRefreshError,
            );
        }

        return $this->generateRedirectResponseWithStatus(
            uri: $redirectUri,
            status: PickwareAccountConnectionStatus::Success,
        );
    }

    private function generateRedirectResponseWithStatus(string $uri, PickwareAccountConnectionStatus $status): Response
    {
        return new RedirectResponse(
            $this->getUriWithVueRouterQueryParameter($uri, $status)->jsonSerialize(),
        );
    }

    /**
     * Appends a query parameter to the fragment portion of a URI, specifically for Vue.js applications using hash-based
     * routing.
     *
     * In Vue applications that use hash mode (e.g., URLs like http://localhost:8080/#/route), query parameters must be
     * included after the hash (within the fragment) to be accessible via Vue Router's `this.$route.query`. Any query
     * parameters placed before the hash will not be recognized by the Vue Router.
     *
     * This method ensures that the specified query parameter is added inside the fragment portion of the URI so that it
     * can be properly handled by the Vue client-side router.
     */
    private function getUriWithVueRouterQueryParameter(
        string $uri,
        PickwareAccountConnectionStatus $redirectStatus,
    ): Http {
        $parsedUri = Http::new($uri);

        $fragment = $parsedUri->getFragment();
        if ($fragment === '') {
            return $parsedUri->withQuery(self::STATUS_QUERY_PARAMETER . '=' . $redirectStatus->value);
        }

        $fragmentParts = parse_url($fragment);
        $fragmentPath = $fragmentParts['path'] ?? '';
        $fragmentQuery = $fragmentParts['query'] ?? '';
        parse_str($fragmentQuery, $queryParameters);
        $queryParameters[self::STATUS_QUERY_PARAMETER] = $redirectStatus->value;
        $newFragmentQuery = http_build_query($queryParameters);

        return $parsedUri->withFragment($fragmentPath . '?' . $newFragmentQuery);
    }
}
