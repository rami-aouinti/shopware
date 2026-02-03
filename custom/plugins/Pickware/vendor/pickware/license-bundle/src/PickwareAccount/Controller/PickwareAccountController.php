<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\PickwareAccount\Controller;

use League\Uri\Http;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountDisconnectionDevFeatureFlag;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountService;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountServiceException;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class PickwareAccountController
{
    public function __construct(
        #[Autowire(param: 'pickware_license.business_platform_base_url')]
        private readonly string $businessPlatformBaseUrl,
        private readonly PluginInstallationRepository $pluginInstallationRepository,
        private readonly PickwareAccountService $pickwareAccountService,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    #[Route(
        path: '/api/_action/pickware-account/get-pickware-account-shop-url',
        methods: ['GET'],
    )]
    public function getPickwareAccountShopUrl(Context $context): Response
    {
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);
        $pickwareLicense = $pluginInstallation->getPickwareLicense();
        if (!$pickwareLicense) {
            return (new JsonApiError([
                'title' => 'Pickware Account not connected',
                'detail' => 'Cannot create Pickware Account shop URL, because the Pickware Account has not been connected yet.',
            ]))->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        $shopUuid = $pickwareLicense->getShopUuid();
        $organizationUuid = $pickwareLicense->getOrganizationUuid();

        $pickwareAccountShopUrl = Http::new($this->businessPlatformBaseUrl)
            ->withPath("/organization/{$organizationUuid}/shop/{$shopUuid}")
            ->jsonSerialize();

        return new JsonResponse([
            'pickwareAccountShopUrl' => $pickwareAccountShopUrl,
        ]);
    }

    #[Route(
        path: '/api/_action/pickware-account/refresh-pickware-license-lease',
        methods: ['POST'],
    )]
    public function refreshPickwareLicenseLease(Context $context): Response
    {
        try {
            $result = $this->pickwareAccountService->refreshPickwareLicenseLease($context);
        } catch (PickwareAccountServiceException $pickwareAccountServiceException) {
            return $pickwareAccountServiceException->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['refreshResult' => $result], Response::HTTP_OK);
    }

    #[Route(
        path: '/api/_action/pickware-account/disconnect-from-pickware-account',
        methods: ['POST'],
    )]
    public function disconnectFromPickwareAccount(Context $context): Response
    {
        if ($this->featureFlagService->isActive(PickwareAccountDisconnectionDevFeatureFlag::NAME)) {
            try {
                $this->pickwareAccountService->disconnectPickwareAccount($context);
            } catch (PickwareAccountServiceException $pickwareAccountServiceException) {
                return $pickwareAccountServiceException->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        } else {
            $this->pickwareAccountService->clearConnectionToPickwareAccount($context);
        }

        return new Response('', Response::HTTP_OK);
    }
}
