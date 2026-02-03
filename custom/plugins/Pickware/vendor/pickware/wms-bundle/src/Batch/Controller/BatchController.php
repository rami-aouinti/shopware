<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Batch\Controller;

use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PickwareErpStarter\Batch\BatchCreationService;
use Pickware\PickwareErpStarter\Batch\BatchException;
use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class BatchController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntityResponseService $entityResponseService,
        private readonly ?BatchCreationService $batchCreationService,
    ) {}

    /**
     * @param array{id: string, productId: string, number?: string|null, bestBeforeDate?: string|null} $batch
     */
    #[Route(
        path: '/api/_action/pickware-wms/batch/create-batch',
        name: 'api.action.pickware-wms.batch.create-batch',
        methods: ['PUT'],
    )]
    #[JsonValidation(schemaFilePath: 'payload-create-batch.schema.json')]
    public function createBatch(#[JsonParameter] array $batch, Context $context): Response
    {
        if ($this->batchCreationService === null) {
            throw new LogicException('This endpoint should not be called without an compatible Pickware ERP.');
        }

        $existingBatch = $this->entityManager->findByPrimaryKey(
            BatchDefinition::class,
            $batch['id'],
            $context,
        );
        if (!$existingBatch) {
            try {
                $this->batchCreationService->createBatches([$batch], $context);
            } catch (BatchException $e) {
                return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            BatchDefinition::class,
            $batch['id'],
            $context,
        );
    }
}
