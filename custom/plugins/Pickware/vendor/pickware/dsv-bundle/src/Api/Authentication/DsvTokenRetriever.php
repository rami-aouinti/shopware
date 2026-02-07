<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api\Authentication;

use DateInterval;
use Pickware\DsvBundle\Api\DsvApiClientConfig;
use Pickware\DsvBundle\Api\DsvOAuthApiClientFactory;
use Pickware\DsvBundle\Api\Requests\OAuthTokenRequest;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Authentication\Credentials;
use Pickware\ShippingBundle\Authentication\Token;
use Pickware\ShippingBundle\Authentication\TokenRetriever;
use Psr\Clock\ClockInterface;

class DsvTokenRetriever implements TokenRetriever
{
    public function __construct(
        private readonly DsvOAuthApiClientFactory $apiClientFactory,
        private readonly ClockInterface $clock,
    ) {}

    public function retrieveToken(Credentials $credentials): Token
    {
        /** @var DsvApiClientConfig $credentials */
        $dsvApiClient = $this->apiClientFactory->createDsvOAuthApiClient(
            $credentials->shouldUseTestingEndpoint(),
        );

        $response = $dsvApiClient->send(
            new OAuthTokenRequest(
                $credentials->getUsername(),
                $credentials->getPassword(),
            ),
        );
        $responseJson = Json::decodeToArray((string) $response->getBody());

        $expiresInSeconds = $responseJson['expires_in'];
        $tokenEndTime = $this->clock->now()->add(new DateInterval('PT' . $expiresInSeconds . 'S'));

        return new Token(
            $responseJson['access_token'],
            $this->clock->now(),
            $tokenEndTime,
        );
    }
}
