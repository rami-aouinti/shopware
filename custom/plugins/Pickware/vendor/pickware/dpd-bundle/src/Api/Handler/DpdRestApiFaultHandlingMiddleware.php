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
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Pickware\DpdBundle\Api\DpdApiClientException;
use Pickware\PhpStandardLibrary\Json\Json;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DpdRestApiFaultHandlingMiddleware
{
    /**
     * @return Closure(RequestInterface, array<string, mixed>): PromiseInterface<ResponseInterface>
     */
    public function __invoke(callable $handler): Closure
    {
        return fn(RequestInterface $request, array $options) => $handler($request, $options)->then(
            $this->onSuccess(...),
        );
    }

    /**
     * DPD will always return a 200 even though the response has a fault. This will catch the faults and create api
     * client exceptions which we can handle in our label creation flow.
     *
     * @return PromiseInterface<ResponseInterface>
     */
    protected function onSuccess(ResponseInterface $response): PromiseInterface
    {
        $jsonResponse = Json::decodeToObject((string)$response->getBody());

        if (str_contains($jsonResponse->status->type, 'Fault')) {
            return Create::rejectionFor(DpdApiClientException::authenticationFailed($jsonResponse->status));
        }

        return Create::promiseFor($response);
    }
}
