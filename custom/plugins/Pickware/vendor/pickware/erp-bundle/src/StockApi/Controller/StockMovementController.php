<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\StockApi\StockMovementParser;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\StockApi\StockMovementServiceValidationException;
use Pickware\ValidationBundle\JsonValidatorException;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class StockMovementController
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
        private readonly StockMovementParser $stockMovementParser,
        private readonly EntityManager $entityManager,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/stock/move',
        name: 'api.action.pickware-erp.stock.move',
        methods: ['POST'],
    )]
    public function stockMove(Request $request, Context $context): Response
    {
        try {
            $stockMovements = $this->stockMovementParser->parseStockMovementsFromJson($request->getContent());
        } catch (JsonValidatorException $e) {
            return (new JsonApiError([
                'status' => Response::HTTP_BAD_REQUEST,
                'title' => 'Request payload invalid',
                'detail' => $e->getMessage(),
            ]))->toJsonApiErrorResponse();
        }

        // Idempotency check
        $stockMovementIds = array_map(fn($stockMovement) => $stockMovement->getId(), $stockMovements);
        $existingStockMovementCount = count($this->entityManager->findIdsBy(
            StockMovementDefinition::class,
            ['id' => $stockMovementIds],
            $context,
        ));
        if ($existingStockMovementCount === count($stockMovementIds)) {
            return new Response('', Response::HTTP_CREATED);
        }

        try {
            $this->stockMovementService->moveStock($stockMovements, $context);
        } catch (StockMovementServiceValidationException $e) {
            return $e->serializeToJsonApiError()->setStatus(Response::HTTP_CONFLICT)->toJsonApiErrorResponse();
        }

        return new Response('', Response::HTTP_CREATED);
    }
}
