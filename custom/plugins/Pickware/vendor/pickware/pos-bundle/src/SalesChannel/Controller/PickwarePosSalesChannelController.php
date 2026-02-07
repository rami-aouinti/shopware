<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\SalesChannel\Controller;

use JsonException;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwarePos\PickwarePosBundle;
use Pickware\PickwarePos\SalesChannel\PickwarePosSalesChannelService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class PickwarePosSalesChannelController
{
    private PickwarePosSalesChannelService $salesChannelService;
    private SystemConfigService $systemConfigService;
    private EntityManager $entityManager;

    public function __construct(
        PickwarePosSalesChannelService $salesChannelService,
        SystemConfigService $systemConfigService,
        EntityManager $entityManager,
    ) {
        $this->salesChannelService = $salesChannelService;
        $this->systemConfigService = $systemConfigService;
        $this->entityManager = $entityManager;
    }

    #[Route(path: '/api/_action/pickware-pos/create-sales-channel', methods: ['POST'])]
    public function createNewPosSalesChannel(Request $request, Context $context): Response
    {
        try {
            $requestPayload = Json::decodeToArray($request->getContent());
        } catch (JsonException $e) {
            return (new JsonApiError([
                'status' => Response::HTTP_BAD_REQUEST,
                'title' => 'The request body is no valid JSON',
                'detail' => $e->getMessage(),
            ]))->toJsonApiErrorResponse();
        }

        // First gather the payload for sales channel creation and config creation. If something is missing in the
        // request the execution will stop with a PHP error. Doing this before creating any entity will avoid any
        // partly created sales channel or config.
        $salesChannelPayload = $requestPayload['salesChannel'];
        $configurationPayload = $requestPayload['systemConfigs'];
        $paymentMethodIds = array_values(array_unique(array_filter([
            $configurationPayload['posCashPaymentMethod'],
            ...$configurationPayload['posCardPaymentMethods'],
            ...$configurationPayload['posOtherPaymentMethods'],
        ])));
        $salesChannelPayload = array_merge(
            $salesChannelPayload,
            [
                'paymentMethods' => array_map(fn(string $id) => ['id' => $id], $paymentMethodIds),
                'shippingMethods' => [
                    ['id' => $configurationPayload['posShippingMethod']],
                ],
                'paymentMethodId' => $configurationPayload['posCashPaymentMethod'],
                'shippingMethodId' => $configurationPayload['posShippingMethod'],
            ],
        );
        $newSalesChannelConfig = [
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posShippingMethodId' => $configurationPayload['posShippingMethod'],
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posCashPaymentMethodId' => $configurationPayload['posCashPaymentMethod'],
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posCardPaymentMethodIds' => $configurationPayload['posCardPaymentMethods'],
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posOtherPaymentMethodIds' => $configurationPayload['posOtherPaymentMethods'],
        ];

        $salesChannelId = $this->salesChannelService->createNewPickwarePosSalesChannel($salesChannelPayload, $context);

        $currentDefaultPluginConfig = $this->systemConfigService->getDomain(
            PickwarePosBundle::PLUGIN_CONFIG_DOMAIN,
            null,
            false,
        );
        foreach ($newSalesChannelConfig as $key => $newValue) {
            $defaultValue = $currentDefaultPluginConfig[$key] ?? null;
            if ($newValue === $defaultValue) {
                // In this case the value is already set for the "all sales channel" config. We assume that the user
                // wants to inherit the values so do not create a new config entry
                continue;
            }
            if (
                is_array($newValue)
                && is_array($defaultValue)
                && array_diff($newValue, $defaultValue) === array_diff($defaultValue, $newValue)
            ) {
                // same as IF above but special comparison for arrays
                continue;
            }
            $this->systemConfigService->set($key, $newValue, $salesChannelId);
        }

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->entityManager->getByPrimaryKey(SalesChannelDefinition::class, $salesChannelId, $context);

        return new JsonResponse($salesChannel, Response::HTTP_CREATED);
    }
}
