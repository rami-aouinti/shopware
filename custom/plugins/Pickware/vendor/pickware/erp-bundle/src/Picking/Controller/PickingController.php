<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking\Controller;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategy;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\GreaterThan;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class PickingController
{
    public function __construct(
        private readonly PickingStrategy $pickingStrategy,
        private readonly StockMovementService $stockMovementService,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/pick-stock-from-warehouse',
        name: 'api.action.pickware-erp.pick-stock-from-warehouse',
        methods: ['POST'],
    )]
    public function pickStockFromWarehouse(
        #[JsonParameterAsUuid] string $productId,
        #[JsonParameterAsUuid] string $warehouseId,
        #[JsonParameter(validations: [new GreaterThan(0)])] int $quantity,
        Context $context,
    ): JsonResponse {
        $pickingRequest = new PickingRequest(
            productQuantities: new ProductQuantityImmutableCollection([
                new ProductQuantity($productId, $quantity),
            ]),
            sourceStockArea: StockArea::warehouse($warehouseId),
        );

        try {
            $stockMovements = $this->pickingStrategy
                ->calculatePickingSolution($pickingRequest, $context)
                ->createStockMovementsWithDestination(
                    StockLocationReference::unknown(),
                );

            $this->stockMovementService->moveStock($stockMovements, $context);
        } catch (PickingStrategyStockShortageException $e) {
            return $e->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse();
    }
}
