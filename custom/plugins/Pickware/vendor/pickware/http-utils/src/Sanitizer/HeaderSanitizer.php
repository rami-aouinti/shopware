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

class HeaderSanitizer implements HttpSanitizer
{
    private array $sanitizedHeaders;

    public function __construct(array $sanitizedHeaders)
    {
        $this->sanitizedHeaders = array_map(mb_strtolower(...), $sanitizedHeaders);
    }

    public static function createForDefaultAuthHeaders(): self
    {
        return new self([
            'authorization',
            'php-auth-user',
            'php-auth-pw',
        ]);
    }

    public function filterHeader(string $headerName, string $headerValue): string
    {
        if ($headerName) {
            if (in_array(mb_strtolower($headerName), $this->sanitizedHeaders, strict: true)) {
                return '*HIDDEN*';
            }

            return $headerValue;
        }

        return $headerValue;
    }

    public function filterBody(string $body): string
    {
        return $body;
    }
}
