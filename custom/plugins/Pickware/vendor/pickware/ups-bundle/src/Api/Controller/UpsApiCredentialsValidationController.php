<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api\Controller;

use Pickware\UpsBundle\Api\Requests\OAuthTokenRequest;
use Pickware\UpsBundle\Api\UpsApiClientException;
use Pickware\UpsBundle\Api\UpsOAuthApiClientFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class UpsApiCredentialsValidationController
{
    public function __construct(private readonly UpsOAuthApiClientFactory $clientFactory) {}

    #[Route(
        path: '/api/_action/pickware-ups/check-ups-api-credentials',
        name: 'api.action.pickware-ups.check-ups-api-credentials',
        methods: ['POST'],
    )]
    public function checkUpsApiCredentials(Request $request): JsonResponse
    {
        $clientId = (string) $request->get('clientId');
        $clientSecret = (string) $request->get('clientSecret');
        $useTestingEndpoint = (bool) $request->get('useTestingEndpoint');

        $client = $this->clientFactory->createUpsOAuthApiClient($useTestingEndpoint);

        try {
            $client->sendRequest(new OAuthTokenRequest($clientId, $clientSecret));

            return new JsonResponse([
                'valid' => true,
                'reason' => null,
            ]);
        } catch (UpsApiClientException $e) {
            if ($e->getPrevious()->getCode() === Response::HTTP_UNAUTHORIZED && $e->serializeToJsonApiErrors()->count() > 1) {
                // The response code is "unauthorized". Hence the credentials are invalid
                return new JsonResponse([
                    'valid' => false,
                    'reason' => $e->serializeToJsonApiErrors()->getErrors()[1]->getDetail(),
                ]);
            }
            if ($e->getPrevious()->getCode() === Response::HTTP_BAD_REQUEST) {
                // The response code is "bad request" since we sent an empty json body. Hence the credentials are valid.
                return new JsonResponse([
                    'valid' => true,
                    'reason' => null,
                ]);
            }

            throw $e;
        }
    }
}
