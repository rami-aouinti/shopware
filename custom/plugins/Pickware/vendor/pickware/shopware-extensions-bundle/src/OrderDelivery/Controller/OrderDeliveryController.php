<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderDelivery\Controller;

use Pickware\HttpUtils\ResponseFactory;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class OrderDeliveryController
{
    public function __construct(private readonly OrderDeliveryService $orderDeliveryService) {}

    #[Route(
        path: '/api/_action/pickware-shopware-extensions/orders/primary-delivery-states',
        name: 'api.action.pickware-shopware-extensions.orders.primary-delivery-states',
        methods: ['GET'],
    )]
    public function getPrimaryOrderDeliveryStates(Request $request, Context $context): JsonResponse
    {
        $orderIds = $request->get('orderIds', []);

        if (!$orderIds || count($orderIds) === 0) {
            return ResponseFactory::createParameterMissingResponse('orderIds');
        }

        return new JsonResponse([
            'deliveryStates' => $this->orderDeliveryService->getPrimaryOrderDeliveryStates($orderIds, $context),
        ]);
    }
}
