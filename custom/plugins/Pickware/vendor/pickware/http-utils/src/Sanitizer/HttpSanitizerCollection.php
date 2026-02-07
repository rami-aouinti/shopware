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

class HttpSanitizerCollection implements HttpSanitizer
{
    /**
     * @var HttpSanitizer[]
     */
    private array $httpSanitizers;

    public function __construct(HttpSanitizer ...$httpSanitizers)
    {
        $this->httpSanitizers = $httpSanitizers;
    }

    public function filterHeader(string $headerName, string $headerValue): string
    {
        foreach ($this->httpSanitizers as $httpSanitizer) {
            $headerValue = $httpSanitizer->filterHeader($headerName, $headerValue);
        }

        return $headerValue;
    }

    public function filterBody(string $body): string
    {
        foreach ($this->httpSanitizers as $httpSanitizer) {
            $body = $httpSanitizer->filterBody($body);
        }

        return $body;
    }
}
