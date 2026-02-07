<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api\Requests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class OAuthTokenRequest extends Request
{
    public function __construct(string $clientId, string $clientSecret)
    {
        parent::__construct(
            method: 'POST',
            uri: 'oauth/v1/token',
            headers: [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            body: Psr7Utils::streamFor(
                sprintf(
                    'client_id=%s&client_secret=%s&grant_type=client_credentials',
                    $clientId,
                    $clientSecret,
                ),
            ),
        );
    }
}
