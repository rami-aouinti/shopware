<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Authentication;

use Pickware\HttpUtils\Sanitizer\HttpSanitizer;

class OAuthBodySanitizer implements HttpSanitizer
{
    public function filterHeader(string $headerName, string $headerValue): string
    {
        return $headerValue;
    }

    public function filterBody(string $body): string
    {
        return '*HIDDEN*';
    }
}
