<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\HttpUtils\Guzzle;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class GuzzleServiceUnavailableRetryMiddleware
{
    private const RETRIABLE_STATUS_CODES = [
        SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR,
        SymfonyResponse::HTTP_BAD_GATEWAY,
        SymfonyResponse::HTTP_SERVICE_UNAVAILABLE,
        SymfonyResponse::HTTP_GATEWAY_TIMEOUT,
    ];

    private static function decider(int $retries, Request $request, ?Response $response, mixed $exception): bool
    {
        // Handle connection exceptions like timeouts and DNS errors
        if ($exception instanceof ConnectException) {
            return $retries <= 2;
        }

        $response = $exception instanceof RequestException ? $exception->getResponse() : $response;
        if (!$response) {
            return false;
        }

        $isRetriableStatusCode = in_array($response->getStatusCode(), self::RETRIABLE_STATUS_CODES);

        // Retries are indexed starting from 0
        return $isRetriableStatusCode && $retries <= 2;
    }

    /**
     * @param callable $delayFunction Function that accepts the number of retries and returns the number of milliseconds to delay.
     */
    public static function withDelay(callable $delayFunction): callable
    {
        return Middleware::retry(self::decider(...), $delayFunction);
    }

    public static function withExponentialBackoffDelay(): callable
    {
        // If no delay function is provided, Guzzle uses a simple implementation of exponential backoff.
        return Middleware::retry(self::decider(...));
    }
}
