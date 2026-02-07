<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareAccountBundle\ApiClient;

use Closure;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PickwareAccountAuthenticationMiddleware
{
    public function __construct(
        private readonly PickwareAccountAccessTokenProviderInterface $accessTokenProvider,
    ) {}

    public function __invoke(callable $handler): Closure
    {
        return function(Request $request, array $options) use ($handler) {
            $accessToken = $this->accessTokenProvider->getAccessToken();
            if ($accessToken !== null) {
                $request = $request->withAddedHeader('Authorization', 'Bearer ' . $accessToken);
            }

            return $handler($request, $options);
        };
    }
}
