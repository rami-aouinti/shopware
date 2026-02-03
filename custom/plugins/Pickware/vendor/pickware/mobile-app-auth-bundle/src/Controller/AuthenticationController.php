<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Shopware\Core\Framework\Api\Controller\Exception\AuthThrottledException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class AuthenticationController
{
    private AuthorizationServer $authorizationServer;
    private PsrHttpFactory $psrHttpFactory;
    private HttpFoundationFactory $httpFoundationFactory;
    private RateLimiter $rateLimiter;

    public function __construct(
        AuthorizationServer $authorizationServer,
        PsrHttpFactory $psrHttpFactory,
        HttpFoundationFactory $httpFoundationFactory,
        RateLimiter $rateLimiter,
    ) {
        $this->authorizationServer = $authorizationServer;
        $this->psrHttpFactory = $psrHttpFactory;
        $this->httpFoundationFactory = $httpFoundationFactory;
        $this->rateLimiter = $rateLimiter;
    }

    #[Route(
        path: '/api/_action/pickware-mobile-app/oauth/token',
        name: 'api.action.pickware-mobile-app.oauth.token',
        defaults: ['_acl' => ['pickware_mobile_app.oauth']],
        methods: ['POST'],
    )]
    /**
     * @deprecated The route _action/pickware/oauth/token is deprecated and will be removed when the corresponding plugins will receive a major release. Use _action/pickware-mobile-app/oauth/token instead.
     */
    #[Route(
        path: '/api/_action/pickware/oauth/token',
        name: 'api.action.pickware.oauth.token',
        defaults: ['_acl' => ['pickware_mobile_app.oauth']],
        methods: ['POST'],
    )]
    public function token(Request $request): Response
    {
        try {
            $cacheKey = $request->get('username') . '-' . $request->getClientIp();

            $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw new AuthThrottledException($exception->getWaitTime(), $exception);
        }

        $psr7Request = $this->psrHttpFactory->createRequest($request);

        $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, new Psr7Response());

        $this->rateLimiter->reset(RateLimiter::OAUTH, $cacheKey);

        return $this->httpFoundationFactory->createResponse($response);
    }
}
