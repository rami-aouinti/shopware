<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Controller;

use Pickware\PickwareErpStarter\Stock\Indexer\StockIndexer;
use Pickware\PickwareErpStarter\Stock\ReservedStockBreakdownService;
use Pickware\PickwareErpStarter\Stock\WarehouseStockNotAvailableForSaleService;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\TotalStockWriter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class StockController
{
    public function __construct(
        private readonly StockIndexer $stockIndexer,
        private readonly TotalStockWriter $totalStockWriter,
        private readonly WarehouseStockNotAvailableForSaleService $warehouseStockNotAvailableForSaleService,
        private readonly ReservedStockBreakdownService $reservedStockBreakdownService,
    ) {}

    #[JsonValidation(schemaFilePath: 'payload-index-stock-for-products.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/index-stock-for-products',
        name: 'api.action.pickware-erp.index-stock-for-products',
        methods: ['PUT'],
    )]
    public function indexStockForProducts(Request $request, Context $context): JsonResponse
    {
        $this->stockIndexer->handle(new EntityIndexingMessage($request->get('productIds'), null, $context));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[JsonValidation(schemaFilePath: 'set-total-physical-stocks-for-products-payload.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/stock/set-total-physical-stocks-for-products',
        name: 'api.action.pickware-erp.stock.set-total-physical-stocks-for-products',
        methods: ['PUT'],
    )]
    public function setTotalPhysicalStocksForProducts(
        Request $request,
        Context $context,
    ): Response {
        $this->totalStockWriter->setTotalStockForProducts(
            $request->request->all(),
            StockLocationReference::productTotalStockChange(),
            $context,
        );

        return new Response('', Response::HTTP_OK);
    }

    #[Route(
        path: '/api/_action/pickware-erp/stock/get-stock-not-available-for-sale-by-warehouse',
        name: 'api.action.pickware-erp.stock.get-stock-not-available-for-sale-by-warehouse',
        methods: ['POST'],
    )]
    public function getStockNotAvailableForSaleByWarehouse(
        #[JsonParameterAsUuid] string $productId,
        Context $context,
    ): JsonResponse {
        return new JsonResponse($this->warehouseStockNotAvailableForSaleService->getUnavailableStockByWarehouseOfProduct(
            $productId,
            $context,
        ));
    }

    #[Route(
        path: '/api/_action/pickware-erp/stock/get-reserved-stock-breakdown',
        name: 'api.action.pickware-erp.stock.get-reserved-stock-breakdown',
        methods: ['POST'],
    )]
    public function getReservedStockBreakdown(
        #[JsonParameterAsUuid] string $productId,
        Context $context,
    ): JsonResponse {
        return new JsonResponse($this->reservedStockBreakdownService->getReservedStockBreakdown($productId, $context));
    }
}
