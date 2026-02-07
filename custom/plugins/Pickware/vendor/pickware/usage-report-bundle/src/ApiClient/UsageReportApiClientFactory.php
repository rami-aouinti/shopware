<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Pickware\HttpUtils\Guzzle\GuzzleServiceUnavailableRetryMiddleware;

class UsageReportApiClientFactory
{
    public function __invoke(string $businessPlatformBaseUrl): UsageReportApiClientInterface
    {
        return new UsageReportApiClient(
            client: $this->createClient($businessPlatformBaseUrl),
        );
    }

    protected function createClient(string $businessPlatformBaseUrl): Client
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(GuzzleServiceUnavailableRetryMiddleware::withExponentialBackoffDelay());

        $handlerStack->unshift(new UsageReportApiErrorHandlingMiddleware());

        return new Client([
            'base_uri' => $businessPlatformBaseUrl,
            'handler' => $handlerStack,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }
}
