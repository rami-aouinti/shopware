<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api;

use Closure;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\Uuid\Uuid;

class TransactionHeaderMiddleware
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
        $request = $request->withAddedHeader('transId', Uuid::randomHex());

        return $request->withAddedHeader('transactionSrc', 'PickwareUPS');
    }

    public static function createForPickware(): self
    {
        return new self();
    }
}
