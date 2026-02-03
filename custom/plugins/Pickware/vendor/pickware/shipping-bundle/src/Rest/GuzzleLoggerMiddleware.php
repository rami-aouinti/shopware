<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Rest;

use Closure;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use Pickware\ShippingBundle\Http\HttpLogger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class GuzzleLoggerMiddleware
{
    private HttpLogger $httpLogger;

    public function __construct(HttpLogger $logger)
    {
        $this->httpLogger = $logger;
    }

    public function __invoke(callable $handler): Closure
    {
        return fn(RequestInterface $request, array $options) => $handler($request, $options)->then(
            $this->onSuccess($request),
            $this->onFailure($request),
        );
    }

    protected function onSuccess(RequestInterface $request): Closure
    {
        return function(ResponseInterface $response) use ($request) {
            $this->httpLogger->logSuccess($request, $response);

            return $response;
        };
    }

    protected function onFailure(RequestInterface $request): Closure
    {
        return function(Throwable $reason) use ($request) {
            $response = $reason instanceof RequestException ? $reason->getResponse() : null;

            $this->httpLogger->logWarning($reason, $request, $response);

            return Create::rejectionFor($reason);
        };
    }
}
