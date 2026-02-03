<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api\Controller;

use GuzzleHttp\Psr7\Request;
use Pickware\DsvBundle\Api\DsvApiClientConfig;
use Pickware\DsvBundle\Api\DsvApiClientException;
use Pickware\DsvBundle\Api\DsvApiClientFactory;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class DsvApiCredentialsValidationController
{
    public function __construct(private readonly DsvApiClientFactory $clientFactory) {}

    #[Route(
        path: '/api/_action/pickware-dsv/check-dsv-api-credentials',
        name: 'api.action.pickware-dsv.check-dsv-api-credentials',
        methods: ['POST'],
    )]
    public function checkDsvApiCredentials(
        #[JsonParameter] string $username,
        #[JsonParameter] string $password,
        #[JsonParameter] bool $useTestingEndpoint,
    ): JsonResponse {
        $client = $this->clientFactory->createDsvApiClient(new DsvApiClientConfig(
            $username,
            $password,
            $useTestingEndpoint,
        ));

        try {
            // Send an empty request to the "bookings" endpoint to check if the credentials are valid.
            // This request will fail because the body is empty, but the response code will be "bad request" instead of "unauthorized".
            $client->sendRequest(new Request(
                'POST',
                'booking/v2/bookings/validate',
                [
                    'Content-Type' => 'application/json',
                ],
                Json::stringify([]),
            ));

            return new JsonResponse(true);
        } catch (DsvApiClientException $e) {
            if ($e->getPrevious()->getCode() === Response::HTTP_UNAUTHORIZED) {
                // The response code is "unauthorized". Hence the credentials are invalid.
                return new JsonResponse(false);
            }
            if ($e->getPrevious()->getCode() === Response::HTTP_BAD_REQUEST) {
                // The response code is "bad request" since we sent an empty json body. Hence the credentials are valid.
                return new JsonResponse(true);
            }

            throw $e;
        }
    }
}
