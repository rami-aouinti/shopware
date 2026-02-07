<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api\Authentication;

use DateTimeZone;
use Pickware\DpdBundle\Api\DpdApiClientConfig;
use Pickware\DpdBundle\Api\DpdRestApiClientFactory;
use Pickware\DpdBundle\Api\Requests\GetAuthRequest;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Authentication\Credentials;
use Pickware\ShippingBundle\Authentication\Token;
use Pickware\ShippingBundle\Authentication\TokenRetriever;
use Psr\Clock\ClockInterface;

class DpdTokenRetriever implements TokenRetriever
{
    public function __construct(
        private readonly DpdRestApiClientFactory $apiClientFactory,
        private readonly ClockInterface $clock,
    ) {}

    public function retrieveToken(Credentials $credentials): Token
    {
        /** @var DpdApiClientConfig $credentials */
        $dpdApiClient = $this->apiClientFactory->createDpdLoginServiceApiClient(
            $credentials->shouldUseTestingEndpoint(),
            $credentials->getLocaleCode(),
        );
        $now = $this->clock->now()->setTimezone(new DateTimeZone('Europe/Berlin'));
        $response = $dpdApiClient->sendRequest(
            new GetAuthRequest($credentials->getDelisId(), $credentials->getPassword()),
        );
        $responseJson = Json::decodeToArray((string) $response->getBody());

        $tokenEndTime = match ($now->format('H')) {
            '00', '01', '02' => $now->setTime(3, 0),
            default => $now->setTime(3, 0)->modify('+1 day'),
        };

        return new Token(
            $responseJson['getAuthResponse']['return']['authToken'],
            $now,
            $tokenEndTime,
        );
    }
}
