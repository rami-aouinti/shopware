<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\OidcClientBundle\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Pickware\HttpUtils\Guzzle\GuzzleServiceUnavailableRetryMiddleware;

class GuzzleHttpClientFactory
{
    public function createHttpClientWithRetries(?callable $handler = null): Client
    {
        $handlerStack = HandlerStack::create($handler);
        $handlerStack->push(GuzzleServiceUnavailableRetryMiddleware::withExponentialBackoffDelay());

        return new Client(['handler' => $handlerStack]);
    }
}
