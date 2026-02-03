<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking\Controller;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\GreaterThan;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class StockingController
{
    public function __construct(
        private readonly StockingStrategy $stockingStrategy,
        private readonly StockMovementService $stockMovementService,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/move-stock-into-warehouse',
        name: 'api.action.pickware-erp.move-stock-into-warehouse',
        methods: ['POST'],
    )]
    public function moveStockIntoWarehouse(
        #[JsonParameterAsUuid] string $productId,
        #[JsonParameterAsUuid] string $warehouseId,
        #[JsonParameter(validations: [new GreaterThan(0)])] int $quantity,
        Context $context,
    ): JsonResponse {
        $stockingRequest = new StockingRequest(
            productQuantities: new ProductQuantityImmutableCollection([
                new ProductQuantity($productId, $quantity),
            ]),
            warehouseId: StockArea::warehouse($warehouseId),
        );

        $stockMovements = $this->stockingStrategy
            ->calculateStockingSolution($stockingRequest, $context)
            ->createStockMovementsWithSource(
                StockLocationReference::unknown(),
            );

        $this->stockMovementService->moveStock($stockMovements, $context);

        return new JsonResponse();
    }
}
