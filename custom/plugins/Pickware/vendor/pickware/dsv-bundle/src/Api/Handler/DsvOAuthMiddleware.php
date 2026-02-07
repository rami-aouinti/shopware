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
use Pickware\DsvBundle\Api\DsvApiClientConfig;
use Pickware\ShippingBundle\Authentication\TokenRetriever;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DsvOAuthMiddleware
{
    public function __construct(
        private readonly DsvApiClientConfig $dsvApiClientConfig,
        private readonly TokenRetriever $tokenRetriever,
    ) {}

    public function __invoke(callable $handler): Closure
    {
        return function(Request $request, array $options) use ($handler) {
            $bearerToken = $this->tokenRetriever->retrieveToken($this->dsvApiClientConfig)->getStringRepresentation();
            $request = $request->withAddedHeader(header: 'Authorization', value: 'Bearer ' . $bearerToken);

            return $handler($request, $options);
        };
    }
}
