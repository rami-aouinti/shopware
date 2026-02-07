<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Config\Controller;

use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\Config\GlobalPluginConfig;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class ConfigController
{
    public function __construct(
        private readonly Config $config,
        private readonly GlobalPluginConfig $globalPluginConfig,
        private readonly FeatureFlagRegistry $featureFlagRegistry,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/set-default-warehouse',
        methods: ['POST'],
    )]
    public function setDefaultWarehouse(
        #[JsonParameterAsUuid] string $warehouseId,
    ): JsonResponse {
        $this->config->setDefaultWarehouseId($warehouseId);

        return new JsonResponse();
    }

    #[Route(
        path: '/api/_action/pickware-erp/set-default-receiving-warehouse',
        methods: ['POST'],
    )]
    public function setDefaultReceivingWarehouse(
        #[JsonParameterAsUuid] string $warehouseId,
    ): JsonResponse {
        $this->config->setDefaultReceivingWarehouseId($warehouseId);

        return new JsonResponse();
    }

    #[Route(
        path: '/api/_action/pickware-erp/get-stock-movement-comments',
        name: 'api.action.pickware-erp.get-stock-movement-comments',
        methods: ['POST'],
    )]
    public function getStockMovementComments(Request $request): JsonResponse
    {
        return new JsonResponse([
            'stockMovementComments' => $this->globalPluginConfig->getDefaultStockMovementComments(),
        ]);
    }

    #[Route(
        path: '/api/_action/pickware-erp/toggle-shopware-features',
        name: 'api.action.pickware-erp.toggle-shopware-features',
        methods: ['POST'],
    )]
    public function toggleShopwareFeatures(#[JsonParameter] array $changedFeatureFlags): JsonResponse
    {
        foreach ($changedFeatureFlags as $featureFlag) {
            if ($featureFlag['isActive']) {
                $this->featureFlagRegistry->enable($featureFlag['name']);
            } else {
                $this->featureFlagRegistry->disable($featureFlag['name']);
            }
        }

        return new JsonResponse();
    }
}
