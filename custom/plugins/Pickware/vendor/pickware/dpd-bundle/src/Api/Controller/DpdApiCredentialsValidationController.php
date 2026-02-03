<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\DpdBundle\Api\DpdApiClientException;
use Pickware\DpdBundle\Api\DpdRestApiClientFactory;
use Pickware\DpdBundle\Api\Requests\GetAuthRequest;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class DpdApiCredentialsValidationController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DpdRestApiClientFactory $clientFactory,
    ) {}

    #[Route(path: '/api/_action/pickware-dpd/check-dpd-api-credentials', name: 'api.action.pickware-dpd.check-dpd-api-credentials', methods: ['POST'])]
    public function checkDpdApiCredentials(Request $request, Context $context): JsonResponse
    {
        $delisId = (string) $request->get('delisId');
        $password = (string) $request->get('password');
        $useTestingEndpoint = (bool) $request->get('useTestingEndpoint');

        $user = $this->entityManager->getByPrimaryKey(
            UserDefinition::class,
            ContextExtension::getUserId($context),
            $context,
            ['locale'],
        );

        $client = $this->clientFactory->createDpdLoginServiceApiClient($useTestingEndpoint, $user->getLocale()?->getCode() ?? 'en-GB');

        try {
            $client->sendRequest(new GetAuthRequest($delisId, $password));

            return new JsonResponse([
                'valid' => true,
            ]);
        } catch (DpdApiClientException $e) {
            return new JsonResponse([
                'valid' => false,
                'code' => $e->serializeToJsonApiError()->getCode(),
                'message' => $e->serializeToJsonApiError()->getDetail(),
            ]);
        }
    }
}
