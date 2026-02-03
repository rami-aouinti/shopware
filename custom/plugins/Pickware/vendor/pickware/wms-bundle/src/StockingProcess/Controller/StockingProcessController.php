<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptException;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovementServiceValidationException;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessEntity;
use Pickware\PickwareWms\StockingProcess\StockingItem;
use Pickware\PickwareWms\StockingProcess\StockingProcessCleanupService;
use Pickware\PickwareWms\StockingProcess\StockingProcessCreation;
use Pickware\PickwareWms\StockingProcess\StockingProcessDefermentReceiptContentGenerator;
use Pickware\PickwareWms\StockingProcess\StockingProcessDefermentReceiptDocumentGenerator;
use Pickware\PickwareWms\StockingProcess\StockingProcessException;
use Pickware\PickwareWms\StockingProcess\StockingProcessService;
use Pickware\PickwareWms\StockingProcess\StockingProcessStateMachine;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class StockingProcessController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockingProcessService $stockingProcessService,
        private readonly StockingProcessCreation $stockingProcessCreation,
        private readonly EntityResponseService $entityResponseService,
        private readonly StockingProcessCleanupService $stockingProcessCleanupService,
        private readonly StockingProcessDefermentReceiptContentGenerator $stockingProcessDefermentReceiptContentGenerator,
        private readonly StockingProcessDefermentReceiptDocumentGenerator $stockingProcessDefermentReceiptDocumentGenerator,
    ) {}

    #[JsonValidation(schemaFilePath: 'payload-create-and-start-stocking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/create-and-start-stocking-process', methods: ['PUT'])]
    public function createAndStartStockingProcess(Request $request, Context $context): Response
    {
        $stockingProcessPayload = $request->get('stockingProcess');
        $existingStockingProcess = $this->entityManager->findByPrimaryKey(
            StockingProcessDefinition::class,
            $stockingProcessPayload['id'],
            $context,
        );

        // This is an idempotency check. If a stocking process with the same ID already exist we assume this action
        // has already been executed.
        if (!$existingStockingProcess) {
            try {
                $this->entityManager->runInTransactionWithRetry(
                    function() use ($stockingProcessPayload, $context): void {
                        $this->stockingProcessCreation->createStockingProcess($stockingProcessPayload, $context);
                        $this->stockingProcessService->startStockingProcess($stockingProcessPayload['id'], $context);
                    },
                );
            } catch (StockingProcessException $exception) {
                return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            } catch (StockMovementServiceValidationException $exception) {
                return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            entityDefinitionClass: StockingProcessDefinition::class,
            entityPrimaryKey: $stockingProcessPayload['id'],
            context: $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-stock-item.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/stock-item', methods: ['PUT'])]
    public function stockItem(Request $request, Context $context): Response
    {
        $stockingProcessId = $request->get('stockingProcessId');

        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($context, $stockingProcessId, $request): void {
                    // The stocking process is locked here to ensure idempotency e.g. in case of long lock wait times,
                    // where the ID-based idempotency check is not sufficient.
                    // See https://github.com/pickware/shopware-plugins/issues/7839#issuecomment-2658960921 for further
                    // information.
                    $this->entityManager->lockPessimistically(
                        StockingProcessDefinition::class,
                        ['id' => $stockingProcessId],
                        $context,
                    );

                    // The idempotency key is used as an ID for the stock movement to detect if the item was already
                    // stocked.
                    $stockMovementId = $request->get('idempotencyKey');
                    $existingStockMovement = $this->entityManager->findByPrimaryKey(
                        StockMovementDefinition::class,
                        $stockMovementId,
                        $context,
                    );

                    // This is an idempotency check. If a stock movement with the same ID already exist we assume this
                    // action has already been executed.
                    if (!$existingStockMovement) {
                        $item = new StockingItem(
                            stockingProcessId: $stockingProcessId,
                            destination: StockLocationReference::create($request->get('stockLocation')),
                            productId: $request->get('productId'),
                            batchId: $request->get('batchId'),
                            quantity: $request->get('quantity'),
                            idempotencyKey: $stockMovementId,
                        );
                        $this->stockingProcessService->stockItem($item, $context);
                    }
                },
            );
        } catch (StockingProcessException $exception) {
            return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        } catch (StockMovementServiceValidationException $exception) {
            return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            entityDefinitionClass: StockingProcessDefinition::class,
            entityPrimaryKey: $stockingProcessId,
            context: $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-stock-stocking-process-completely.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/stock-stocking-process-completely', methods: ['PUT'])]
    public function stockStockingProcessCompletely(
        #[JsonParameterAsUuid] string $stockingProcessId,
        Context $context,
    ): Response {
        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($context, $stockingProcessId): void {
                    $this->entityManager->lockPessimistically(
                        StockingProcessDefinition::class,
                        ['id' => $stockingProcessId],
                        $context,
                    );

                    /** @var StockingProcessEntity $stockingProcess */
                    $stockingProcess = $this->entityManager->findByPrimaryKey(
                        StockingProcessDefinition::class,
                        $stockingProcessId,
                        $context,
                        ['lineItems'],
                    );
                    if (!$stockingProcess) {
                        throw StockingProcessException::stockingProcessNotFound($stockingProcessId);
                    }

                    foreach ($stockingProcess->getLineItems() as $lineItem) {
                        $item = new StockingItem(
                            stockingProcessId: $stockingProcessId,
                            destination: $lineItem->getStockLocationReference(),
                            productId: $lineItem->getProductId(),
                            batchId: $lineItem->getBatchId(),
                            quantity: $lineItem->getQuantity(),
                        );
                        $this->stockingProcessService->stockItem($item, $context);
                    }
                },
            );
        } catch (StockingProcessException $exception) {
            return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            entityDefinitionClass: StockingProcessDefinition::class,
            entityPrimaryKey: $stockingProcessId,
            context: $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-defer-stocking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/defer-stocking-process', methods: ['PUT'])]
    public function deferStockingProcess(Request $request, Context $context): Response
    {
        $stockingProcessId = $request->get('stockingProcessId');
        /** @var StockingProcessEntity $stockingProcess */
        $stockingProcess = $this->entityManager->findByPrimaryKey(
            StockingProcessDefinition::class,
            $stockingProcessId,
            $context,
            ['state'],
        );
        try {
            if (!$stockingProcess) {
                throw StockingProcessException::stockingProcessNotFound($stockingProcessId);
            }
            if ($stockingProcess->getState()->getTechnicalName() !== StockingProcessStateMachine::STATE_DEFERRED) {
                $this->stockingProcessService->deferStockingProcess($stockingProcessId, $context);
            }
        } catch (StockingProcessException $exception) {
            return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            entityDefinitionClass: StockingProcessDefinition::class,
            entityPrimaryKey: $stockingProcessId,
            context: $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-continue-stocking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/continue-stocking-process', methods: ['PUT'])]
    public function continueStockingProcess(Request $request, Context $context): Response
    {
        $stockingProcessId = $request->get('stockingProcessId');
        /** @var StockingProcessEntity $stockingProcess */
        $stockingProcess = $this->entityManager->findByPrimaryKey(
            StockingProcessDefinition::class,
            $stockingProcessId,
            $context,
            ['state'],
        );
        try {
            if (!$stockingProcess) {
                throw StockingProcessException::stockingProcessNotFound($stockingProcessId);
            }
            if ($stockingProcess->getState()->getTechnicalName() !== StockingProcessStateMachine::STATE_IN_PROGRESS) {
                $this->stockingProcessService->continueStockingProcess($stockingProcessId, $context);
            }
        } catch (StockingProcessException $exception) {
            return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            entityDefinitionClass: StockingProcessDefinition::class,
            entityPrimaryKey: $stockingProcessId,
            context: $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-take-over-stocking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/take-over-stocking-process', methods: ['PUT'])]
    public function takeOverStockingProcess(Request $request, Context $context): Response
    {
        $stockingProcessId = $request->get('stockingProcessId');
        /** @var StockingProcessEntity $stockingProcess */
        $stockingProcess = $this->entityManager->findByPrimaryKey(
            StockingProcessDefinition::class,
            $stockingProcessId,
            $context,
            ['state'],
        );
        try {
            if (!$stockingProcess) {
                throw StockingProcessException::stockingProcessNotFound($stockingProcessId);
            }
            $this->stockingProcessService->takeOver($stockingProcessId, $context);
        } catch (StockingProcessException $exception) {
            return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            StockingProcessDefinition::class,
            $stockingProcessId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-complete-stocking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/complete-stocking-process', methods: ['PUT'])]
    public function completeStockingProcess(Request $request, Context $context): Response
    {
        $stockingProcessId = $request->get('stockingProcessId');

        try {
            return $this->entityManager->runInTransactionWithRetry(function() use ($context, $stockingProcessId) {
                // We need a lock here otherwise the forthcoming select would produce phantom reads in the
                // transaction of the complete-method.
                $this->entityManager->lockPessimistically(
                    StockingProcessDefinition::class,
                    ['id' => $stockingProcessId],
                    $context,
                );

                /** @var StockingProcessEntity $stockingProcess */
                $stockingProcess = $this->entityManager->findByPrimaryKey(
                    StockingProcessDefinition::class,
                    $stockingProcessId,
                    $context,
                );

                // Completing a stocking process will also delete it. If the requested stocking process does not exist,
                // we consider this action to be completed already. We willingly ignore the scenario that the ID does
                // not and did not belong to any stocking process in the first place.
                // Even though the "first" request will complete and delete the entity and returns an entity detail
                // response, we cannot do this in a "second" request for the same stocking process, because it is
                // already deleted. Return an HTTP_OK instead.
                if (!$stockingProcess) {
                    return new JsonResponse(null, Response::HTTP_OK);
                }

                $this->stockingProcessService->completeStockingProcess($stockingProcessId, $context);

                $response = $this->entityResponseService->makeEntityDetailResponse(
                    entityDefinitionClass: StockingProcessDefinition::class,
                    entityPrimaryKey: $stockingProcessId,
                    context: $context,
                );

                $this->stockingProcessCleanupService->deleteStockingProcess($stockingProcessId, $context);

                return $response;
            });
        } catch (StockingProcessException $exception) {
            return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        } catch (GoodsReceiptException $exception) {
            return $exception->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(path: '/api/_action/pickware-wms/stocking-process-deferment-receipt', methods: ['GET'])]
    public function getStockingProcessDefermentReceipt(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^[0-9a-f]{32}$/'])]
        string $stockingProcessId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^[0-9a-f]{32}$/'])]
        ?string $languageId,
        Context $context,
    ): Response {
        $languageId ??= Defaults::LANGUAGE_SYSTEM;

        $templateVariables = $this->stockingProcessDefermentReceiptContentGenerator->generateForStockingProcess(
            $stockingProcessId,
            $languageId,
            $context,
        );
        $renderedDocument = $this->stockingProcessDefermentReceiptDocumentGenerator->generate($templateVariables, $languageId, $context);

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }
}
