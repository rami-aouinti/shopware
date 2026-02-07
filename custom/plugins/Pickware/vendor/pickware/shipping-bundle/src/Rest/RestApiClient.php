<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Rest;

use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RestApiClient
{
    private Client $restClient;

    public function __construct(Client $restClient)
    {
        $this->restClient = $restClient;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->restClient->send($request);
    }

    public function getRestClient(): Client
    {
        return $this->restClient;
    }
}
