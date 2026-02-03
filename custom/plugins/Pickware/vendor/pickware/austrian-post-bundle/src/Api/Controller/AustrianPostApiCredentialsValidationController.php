<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api\Controller;

use Pickware\AustrianPostBundle\Api\AustrianPostApiClientConfig;
use Pickware\AustrianPostBundle\Api\AustrianPostShipmentServiceApiApplicationException;
use Pickware\AustrianPostBundle\Api\AustrianPostSoapApiClientFactory;
use Pickware\ShippingBundle\Soap\SoapRequest;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class AustrianPostApiCredentialsValidationController
{
    public function __construct(private readonly AustrianPostSoapApiClientFactory $clientFactory) {}

    #[Route(
        path: '/api/_action/pickware-austrian-post/check-austrian-post-api-credentials',
        name: 'api.action.pickware-austrian-post.check-austrian-post-api-credentials',
        methods: ['POST'],
    )]
    public function checkAustrianPostApiCredentials(
        #[JsonParameter] int $clientId,
        #[JsonParameter] int $orgUnitId,
        #[JsonParameter] string $orgUnitGuid,
        #[JsonParameter] bool $useTestingEndpoint,
    ): JsonResponse {
        $client = $this->clientFactory->createAuthenticatedAustrianPostShipmentServiceApiClient(
            new AustrianPostApiClientConfig(
                clientId: $clientId,
                orgUnitId: $orgUnitId,
                orgUnitGuid: $orgUnitGuid,
                shouldUseTestingEndpoint: $useTestingEndpoint,
            ),
        );

        try {
            // Send an empty request to the "ImportShipment" endpoint to check if the credentials are valid.
            // This request will fail because the shipment information are missing,
            // but the response will contain the shipment information are missing instead of a credential error.
            $client->sendRequest(
                new SoapRequest(
                    'ImportShipment',
                    [
                        'row' => [],
                    ],
                ),
            );

            return new JsonResponse(true);
        } catch (AustrianPostShipmentServiceApiApplicationException $e) {
            return new JsonResponse(!$e->isAuthorizationError());
        }
    }
}
