<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Api\Controller;

use Pickware\SwissPostBundle\Api\Requests\OAuthTokenRequest;
use Pickware\SwissPostBundle\Api\SwissPostApiClientException;
use Pickware\SwissPostBundle\Api\SwissPostOAuthApiClientFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class SwissPostApiCredentialsValidationController
{
    public function __construct(private readonly SwissPostOAuthApiClientFactory $clientFactory) {}

    #[Route(path: 'api/_action/pickware-swiss-post/check-swiss-post-api-credentials', name: 'api.action.pickware-swiss-post.check-swiss-post-api-credentials', methods: 'POST')]
    public function checkSwissPostApiCredentials(Request $request): JsonResponse
    {
        $clientId = (string) $request->get('clientId');
        $clientSecret = (string) $request->get('clientSecret');

        $client = $this->clientFactory->createSwissPostOAuthApiClient();

        try {
            $client->sendRequest(new OAuthTokenRequest($clientId, $clientSecret));

            return new JsonResponse([
                'valid' => true,
            ]);
        } catch (SwissPostApiClientException $e) {
            if ($e->getPrevious()->getCode() === Response::HTTP_UNAUTHORIZED) {
                // The response code is "unauthorized". Hence the credentials are invalid
                return new JsonResponse([
                    'valid' => false,
                    'reason' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
