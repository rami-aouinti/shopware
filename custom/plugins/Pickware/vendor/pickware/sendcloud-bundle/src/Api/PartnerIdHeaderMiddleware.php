<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Api;

use Closure;
use GuzzleHttp\Psr7\Request;

class PartnerIdHeaderMiddleware
{
    public function __invoke(callable $handler): Closure
    {
        return function(Request $request, array $options) use ($handler) {
            $request = $this->addTransactionHeader($request);

            return $handler($request, $options);
        };
    }

    private function addTransactionHeader(Request $request): Request
    {
        return $request->withAddedHeader('Sendcloud-Partner-Id', '8d0d2cb0-3eb0-492f-9916-25777763fc02');
    }

    public static function createForPickware(): self
    {
        return new self();
    }
}
