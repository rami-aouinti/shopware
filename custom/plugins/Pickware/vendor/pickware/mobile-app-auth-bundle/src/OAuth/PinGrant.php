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
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\RequestEvent;
use Psr\Http\Message\ServerRequestInterface;

class PinGrant extends PasswordGrant
{
    public const PIN_GRANT_TYPE = 'pin';

    public function __construct(
        UserRepositoryInterface $userRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
    ) {
        parent::__construct($userRepository, $refreshTokenRepository);

        $this->refreshTokenTTL = new DateInterval('P1W');
    }

    protected function validateUser(
        ServerRequestInterface $request,
        ClientEntityInterface $client,
    ): UserEntityInterface {
        $username = $this->getRequestParameter('username', $request);
        if ($username === null) {
            throw OAuthServerException::invalidRequest('username');
        }

        $pin = $this->getRequestParameter(self::PIN_GRANT_TYPE, $request);
        if ($pin === null) {
            throw OAuthServerException::invalidRequest(self::PIN_GRANT_TYPE);
        }

        $user = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $pin,
            $this->getIdentifier(),
            $client,
        );
        if ($user instanceof UserEntityInterface === false) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw OAuthServerException::invalidCredentials();
        }

        return $user;
    }

    public function getIdentifier(): string
    {
        return self::PIN_GRANT_TYPE;
    }
}
