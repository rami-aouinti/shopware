<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\HttpUtils\Sanitizer;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpSanitizing
{
    /**
     * @var HttpSanitizer[]
     */
    private array $httpSanitizers;

    public function __construct(HttpSanitizer ...$httpSanitizers)
    {
        $this->httpSanitizers = $httpSanitizers;
    }

    public function sanitizeRequest(RequestInterface $request): RequestInterface
    {
        [$body, $headers] = $this->sanitizeMessage($request);

        return new Request(
            $request->getMethod(),
            $request->getUri(),
            $headers,
            $body,
            $request->getProtocolVersion(),
        );
    }

    public function sanitizeResponse(ResponseInterface $response): ResponseInterface
    {
        [$body, $headers] = $this->sanitizeMessage($response);

        return new Response(
            $response->getStatusCode(),
            $headers,
            $body,
            $response->getProtocolVersion(),
            $response->getReasonPhrase(),
        );
    }

    private function sanitizeMessage(MessageInterface $request): array
    {
        $body = (string) $request->getBody();
        $headers = $request->getHeaders();
        foreach ($this->httpSanitizers as $httpSanitizer) {
            $body = $httpSanitizer->filterBody($body);
            foreach ($headers as $headerName => $headerValues) {
                foreach ($headerValues as $key => $headerValue) {
                    $headers[$headerName][$key] = $httpSanitizer->filterHeader($headerName, $headerValue);
                }
            }
        }

        return [
            $body,
            $headers,
        ];
    }
}
