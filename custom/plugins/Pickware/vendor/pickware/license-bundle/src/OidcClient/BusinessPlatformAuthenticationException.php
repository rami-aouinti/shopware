<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\OidcClient;

use Exception;

class BusinessPlatformAuthenticationException extends Exception
{
    public static function loginFailed(int $statusCode, string $responseBody): self
    {
        return new self(sprintf(
            'Login failed with status code %d: %s',
            $statusCode,
            $responseBody,
        ));
    }
}
