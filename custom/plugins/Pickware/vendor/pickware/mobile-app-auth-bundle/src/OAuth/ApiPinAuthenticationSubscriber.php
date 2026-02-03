<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\OAuth;

use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiPinAuthenticationSubscriber implements EventSubscriberInterface
{
    private AuthorizationServer $authorizationServer;
    private UserRepositoryInterface $userRepository;
    private RefreshTokenRepositoryInterface $refreshTokenRepository;

    public function __construct(
        AuthorizationServer $authorizationServer,
        UserRepositoryInterface $userRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
    ) {
        $this->authorizationServer = $authorizationServer;
        $this->userRepository = $userRepository;
        $this->refreshTokenRepository = $refreshTokenRepository;
    }

    public static function getSubscribedEvents(): array
    {
        // Shopware's OAuth subscriber subscribes itself at position 128, use the same here. This ensures that other
        // subscribers which expect the authorization server to be configured already can use an appropriate priority
        // number and do not need to distinguish between shopware's priority and ours.
        return [
            KernelEvents::REQUEST => [
                [
                    'setupPinBasedOAuth',
                    128,
                ],
            ],
        ];
    }

    public function setupPinBasedOAuth(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $pinGrant = new PinGrant($this->userRepository, $this->refreshTokenRepository);
        $tenMinuteInterval = new DateInterval('PT10M');
        $this->authorizationServer->enableGrantType($pinGrant, $tenMinuteInterval);
    }
}
