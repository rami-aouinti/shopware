<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api;

use Closure;
use GuzzleHttp\Psr7\Request;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Rest\RestApiClient;
use Pickware\UpsBundle\Api\Requests\OAuthTokenRequest;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class OAuthMiddleware
{
    public function __construct(
        private readonly UpsApiClientConfig $config,
        private readonly RestApiClient $authClient,
    ) {}

    public function __invoke(callable $handler): Closure
    {
        return function(Request $request, array $options) use ($handler) {
            $request = $request->withAddedHeader('Authorization', 'Bearer ' . $this->getAccessToken());

            return $handler($request, $options);
        };
    }

    private function getAccessToken(): string
    {
        $response = $this->authClient->sendRequest(new OAuthTokenRequest($this->config->getClientId(), $this->config->getClientSecret()));
        $data = Json::decodeToArray($response->getBody()->__toString());

        return $data['access_token'];
    }
}
