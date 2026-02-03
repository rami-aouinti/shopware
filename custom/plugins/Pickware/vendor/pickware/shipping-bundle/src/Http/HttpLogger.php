<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Http;

use GuzzleHttp\Psr7\Message;
use Pickware\HttpUtils\Sanitizer\HttpSanitizing;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class HttpLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?HttpSanitizing $httpSanitizing,
    ) {}

    public function logSuccess(RequestInterface $request, ResponseInterface $response): void
    {
        $this->logger->debug('HTTP request successful', $this->getContext($request, $response));
    }

    /**
     * Failing HTTP requests that are not critical errors (that we immediately want to look into). They can be logged as
     * warnings.
     */
    public function logWarning(Throwable $reason, RequestInterface $request, ?ResponseInterface $response): void
    {
        $this->logger->warning('HTTP request failed: ' . $reason->getMessage(), $this->getContext($request, $response));
    }

    private function getContext(RequestInterface $request, ?ResponseInterface $response): array
    {
        return [
            'request' => Message::toString($this->httpSanitizing?->sanitizeRequest($request) ?? $request),
            'response' => $response ? Message::toString($this->httpSanitizing?->sanitizeResponse($response) ?? $response) : null,
        ];
    }
}
