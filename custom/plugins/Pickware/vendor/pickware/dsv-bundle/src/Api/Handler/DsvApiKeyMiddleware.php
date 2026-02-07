<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api\Handler;

use Closure;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DsvApiKeyMiddleware
{
    public function __construct(private readonly array $apiKeyMapping) {}

    public function __invoke(callable $handler): Closure
    {
        return function(Request $request, array $options) use ($handler) {
            $request = $this->addTransactionHeader($request);

            return $handler($request, $options);
        };
    }

    private function addTransactionHeader(Request $request): Request
    {
        foreach ($this->apiKeyMapping as $path => $apiKey) {
            if (str_starts_with($request->getUri()->getPath(), $path)) {
                return $request->withAddedHeader('DSV-Subscription-Key', $apiKey);
            }
        }

        return $request;
    }
}
