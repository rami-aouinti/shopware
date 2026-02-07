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

class BusinessPlatformHeadlessOidcFlowException extends Exception
{
    public static function unexpectedStatusCode(string $step, int $expected, int $actual): self
    {
        return new self(sprintf(
            'Unexpected status code during %s: expected %d, got %d',
            $step,
            $expected,
            $actual,
        ));
    }

    public static function missingAuthorizationCode(string $redirectUrl): self
    {
        return new self(sprintf(
            'No authorization code found in redirect URL: %s',
            $redirectUrl,
        ));
    }
}
