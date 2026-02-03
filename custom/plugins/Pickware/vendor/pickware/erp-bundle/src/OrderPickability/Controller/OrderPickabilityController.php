<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability\Controller;

use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityCalculatorInterface;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class OrderPickabilityController
{
    public function __construct(
        private readonly OrderPickabilityCalculatorInterface $orderPickabilityCalculator,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/order-pickability/get-pickabilities-for-products-in-order-and-warehouse',
        name: 'api.action.pickware-erp.order-pickability.get-pickabilities-for-products-in-order-and-warehouse',
        methods: ['POST'],
    )]
    public function getPickabilitiesForProductsInOrderAndWarehouse(
        #[JsonParameterAsUuid] string $orderId,
        #[JsonParameterAsUuid] string $warehouseId,
    ): JsonResponse {
        return new JsonResponse([
            'pickabilityByProductId' => $this->orderPickabilityCalculator->calculateProductPickabilitiesForOrderAndWarehouse(
                $orderId,
                $warehouseId,
            )->jsonSerialize(),
        ]);
    }
}
