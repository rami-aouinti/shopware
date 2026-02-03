<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api\Handler;

use Closure;
use GuzzleHttp\Psr7\Utils;
use Pickware\PhpStandardLibrary\Json\Json;
use Psr\Http\Message\RequestInterface;

class DpdRestApiMessageLanguageMiddleware
{
    public function __construct(
        private readonly string $languageKey,
    ) {}

    public function __invoke(callable $handler): Closure
    {
        return function(RequestInterface $request, array $options) use ($handler) {
            $oldRequestBody = Json::decodeToArray(Utils::copyToString($request->getBody()));

            $newRequestBody = Utils::streamFor(Json::stringify(array_merge(
                $oldRequestBody,
                ['messageLanguage' => $this->languageKey],
            )));

            return $handler($request->withBody($newRequestBody), $options);
        };
    }
}
