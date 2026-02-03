<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api\Controller;

use Pickware\DhlExpressBundle\Api\DhlExpressApiClientConfig;
use Pickware\DhlExpressBundle\Api\DhlExpressApiClientException;
use Pickware\DhlExpressBundle\Api\DhlExpressApiClientFactory;
use Pickware\DhlExpressBundle\Api\Requests\CheckCredentialsRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class DhlExpressApiCredentialsValidationController
{
    public function __construct(private readonly DhlExpressApiClientFactory $clientFactory) {}

    #[Route(
        path: '/api/_action/pickware-dhl-express/check-dhl-express-api-credentials',
        name: 'api.action.pickware-dhl-express.check-dhl-express-api-credentials',
        methods: ['POST'],
    )]
    public function checkDhlExpressApiCredentials(Request $request): JsonResponse
    {
        $username = (string) $request->get('username');
        $password = (string) $request->get('password');
        $useTestingEndpoint = (bool) $request->get('useTestingEndpoint');

        $client = $this->clientFactory->createDhlExpressApiClient(new DhlExpressApiClientConfig(
            $username,
            $password,
            $useTestingEndpoint,
        ));

        try {
            $client->sendRequest(new CheckCredentialsRequest());

            return new JsonResponse(true);
        } catch (DhlExpressApiClientException $e) {
            if ($e->getPrevious()->getCode() === Response::HTTP_BAD_REQUEST) {
                // When the credentials are invalid, the response code is "bad request" instead of "unauthorized". The
                // body of the response contains an "Invalid Credentials" message.
                return new JsonResponse(false);
            }
            if ($e->getPrevious()->getCode() === Response::HTTP_UNPROCESSABLE_ENTITY) {
                // The response code is "unprocessable entity" since we sent an empty json object. Hence the credentials are valid.
                // Otherwise, "bad request" would be returned.
                return new JsonResponse(true);
            }

            throw $e;
        }
    }
}
