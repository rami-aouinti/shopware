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

use InvalidArgumentException;

class SecureUrlValidator
{
    public static function validate(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!$host || !$scheme) {
            throw new InvalidArgumentException('The provided URL is invalid');
        }

        if (
            $scheme !== 'https'
            && !(in_array(
                $host,
                [
                    'localhost',
                    '127.0.0.1',
                ],
            ))
        ) {
            throw new InvalidArgumentException('The URL must be a secure https URL');
        }
    }
}
