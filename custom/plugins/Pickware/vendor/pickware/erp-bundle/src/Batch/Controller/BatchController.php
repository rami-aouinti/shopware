<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PickwareErpStarter\Batch\BatchCreationService;
use Pickware\PickwareErpStarter\Batch\BatchException;
use Pickware\PickwareErpStarter\Batch\BatchStockAssignmentService;
use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\ValidationBundle\Annotation\JsonParameterAsArrayOfUuids;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @phpstan-import-type BatchCreationPayload from BatchCreationService
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class BatchController
{
    public function __construct(
        private readonly BatchCreationService $batchCreationService,
        private readonly BatchStockAssignmentService $batchStockAssignmentService,
        private readonly EntityResponseService $entityResponseService,
        private readonly EntityManager $entityManager,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/create-batch',
        name: 'api.action.pickware-erp.batch.create-batch',
        methods: ['POST'],
    )]
    #[JsonValidation('batch-creation-payload.schema.json')]
    public function createBatch(
        Request $request,
        Context $context,
    ): Response {
        /** @var BatchCreationPayload $batchPayload */
        $batchPayload = $request->request->all();

        try {
            $this->batchCreationService->createBatches([$batchPayload], $context);
        } catch (BatchException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string> $batchIds
     */
    #[Route(
        path: '/api/_action/pickware-erp/batch/delete-batches',
        name: 'api.action.pickware-erp.batch.delete-batches',
        methods: ['POST'],
    )]
    public function deleteBatches(
        #[JsonParameterAsArrayOfUuids] array $batchIds,
        Context $context,
    ): Response {
        $this->entityManager->delete(
            BatchDefinition::class,
            $batchIds,
            $context,
        );

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/pickware-erp/batch/assign-stock-to-batch',
        name: 'api.action.pickware-erp.batch.assign-stock-to-batch',
        methods: ['POST'],
    )]
    public function assignStockToBatch(
        #[JsonParameterAsUuid] string $stockId,
        #[JsonParameterAsUuid] string $batchId,
        Context $context,
    ): Response {
        try {
            $this->batchStockAssignmentService->assignStockToBatch($stockId, $batchId, $context);
        } catch (BatchException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            StockDefinition::class,
            $stockId,
            $context,
        );
    }
}
