<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api\Requests;

use GuzzleHttp\Psr7\Request;
use Pickware\PhpStandardLibrary\Json\Json;

class GetAuthRequest extends Request
{
    public function __construct(string $delisId, string $password)
    {
        parent::__construct(
            'POST',
            'getAuth',
            ['Content-Type' => 'application/json'],
            Json::stringify([
                'delisId' => $delisId,
                'password' => $password,
            ]),
        );
    }
}
