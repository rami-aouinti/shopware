<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\OidcClientBundle\Client;

use Exception;

class Oauth2PkceClientException extends Exception
{
    public static function invalidState(): self
    {
        return new self('Invalid state');
    }

    public static function missingAuthorizationCode(): self
    {
        return new self('No "code" parameter provided');
    }
}
