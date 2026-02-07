<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareAccountBundle\ApiClient;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Pickware\HttpUtils\Guzzle\GuzzleServiceUnavailableRetryMiddleware;

class PickwareAccountApiClientFactory
{
    public function __invoke(
        string $businessPlatformBaseUrl,
        PickwareAccountAccessTokenProviderInterface $pickwareAccountAccessTokenProvider,
    ): PickwareAccountApiClient {
        return new PickwareAccountApiClient(
            client: $this->createClient(
                $businessPlatformBaseUrl,
                $pickwareAccountAccessTokenProvider,
            ),
        );
    }

    protected function createClient(
        string $businessPlatformBaseUrl,
        PickwareAccountAccessTokenProviderInterface $pickwareAccountAccessTokenProvider,
        ?Closure $retryMiddlewareDelayFunctionOverride = null,
    ): Client {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(new PickwareAccountAuthenticationMiddleware(
            accessTokenProvider: $pickwareAccountAccessTokenProvider,
        ));

        if ($retryMiddlewareDelayFunctionOverride) {
            $handlerStack->push(GuzzleServiceUnavailableRetryMiddleware::withDelay($retryMiddlewareDelayFunctionOverride));
        } else {
            $handlerStack->push(GuzzleServiceUnavailableRetryMiddleware::withExponentialBackoffDelay());
        }

        $handlerStack->unshift(new PickwareAccountApiErrorHandlingMiddleware());

        return new Client([
            'base_uri' => $businessPlatformBaseUrl,
            'handler' => $handlerStack,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }
}
