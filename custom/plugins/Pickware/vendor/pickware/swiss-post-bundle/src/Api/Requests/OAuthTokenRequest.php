<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Api\Requests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils as Psr7Utils;

class OAuthTokenRequest extends Request
{
    public function __construct(string $clientId, string $clientSecret)
    {
        parent::__construct(
            'POST',
            'OAuth/token',
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => sprintf('Basic %s', base64_encode(sprintf('%s:%s', $clientId, $clientSecret))),
            ],
            Psr7Utils::streamFor('grant_type=client_credentials&scope=DCAPI_BARCODE_READ'),
        );
    }
}
