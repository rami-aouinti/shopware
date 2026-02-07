<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Api\Controller;

use GuzzleHttp\Psr7\Request;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\SendcloudBundle\Api\SendcloudApiClientConfig;
use Pickware\SendcloudBundle\Api\SendcloudApiClientException;
use Pickware\SendcloudBundle\Api\SendcloudApiClientFactory;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class SendcloudApiCredentialsValidationController
{
    public function __construct(private readonly SendcloudApiClientFactory $clientFactory) {}

    #[Route(
        path: '/api/_action/pickware-sendcloud/check-sendcloud-api-credentials',
        name: 'api.action.pickware-sendcloud.check-sendcloud-api-credentials',
        methods: ['POST'],
    )]
    public function checkSendcloudApiCredentials(
        #[JsonParameter] string $publicKey,
        #[JsonParameter] string $secretKey,
    ): JsonResponse {
        $client = $this->clientFactory->createSendcloudApiClient(new SendcloudApiClientConfig(
            $publicKey,
            $secretKey,
        ));

        try {
            // Send an empty request to the "parcels" endpoint to check if the credentials are valid.
            // This request will fail because the body is empty, but the response code will be "bad request" instead of "unauthorized".
            $client->sendRequest(new Request(
                'POST',
                'parcels',
                ['Content-Type' => 'application/json'],
                Json::stringify([]),
            ));

            return new JsonResponse(true);
        } catch (SendcloudApiClientException $e) {
            if ($e->getPrevious()->getCode() === Response::HTTP_UNAUTHORIZED) {
                // The response code is "unauthorized". Hence the credentials are invalid
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
