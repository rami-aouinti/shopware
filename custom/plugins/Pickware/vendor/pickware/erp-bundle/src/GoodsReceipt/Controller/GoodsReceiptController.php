<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Controller;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\GoodsReceipt\Document\GoodsReceiptNoteContentGenerator;
use Pickware\PickwareErpStarter\GoodsReceipt\Document\GoodsReceiptNoteDocumentGenerator;
use Pickware\PickwareErpStarter\GoodsReceipt\Document\GoodsReceiptStockingListContentGenerator;
use Pickware\PickwareErpStarter\GoodsReceipt\Document\GoodsReceiptStockingListDocumentGenerator;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptCreationService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptError;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptException;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptPriceCalculationService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptUpdateService;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Stocking\GoodsReceiptStockDestinationAssignmentService;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Pickware\ValidationBundle\Annotation\JsonParameter;
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
use Symfony\Component\Validator\Constraints\Positive;

/**
 * @phpstan-import-type GoodsReceiptLineItemUpdate from GoodsReceiptUpdateService
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class GoodsReceiptController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly GoodsReceiptCreationService $goodsReceiptCreationService,
        private readonly GoodsReceiptUpdateService $goodsReceiptUpdateService,
        private readonly GoodsReceiptPriceCalculationService $goodsReceiptPriceCalculationService,
        private readonly GoodsReceiptStockingListContentGenerator $goodsReceiptStockingListContentGenerator,
        private readonly GoodsReceiptStockingListDocumentGenerator $goodsReceiptStockingListDocumentGenerator,
        private readonly GoodsReceiptNoteContentGenerator $goodsReceiptNoteContentGenerator,
        private readonly GoodsReceiptNoteDocumentGenerator $goodsReceiptNoteDocumentGenerator,
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly GoodsReceiptStockDestinationAssignmentService $goodsReceiptStockDestinationAssignmentService,
        private readonly StockMovementService $stockMovementService,
        private readonly Connection $connection,
    ) {}

    #[Route(path: 'api/_action/pickware-erp/create-goods-receipt', methods: 'PUT')]
    #[JsonValidation('create-goods-receipt.schema.json')]
    public function createGoodsReceipt(Request $request, Context $context): Response
    {
        $goodsReceiptPayload = $request->get('goodsReceipt');
        $existingGoodsReceipt = $this->entityManager->findByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptPayload['id'],
            $context,
        );
        if ($existingGoodsReceipt === null) {
            try {
                $this->entityManager->runInTransactionWithRetry(
                    function() use ($context, $goodsReceiptPayload): void {
                        $this->goodsReceiptCreationService->createGoodsReceipt($goodsReceiptPayload, $context);
                        $this->goodsReceiptPriceCalculationService->recalculateGoodsReceipts(
                            [$goodsReceiptPayload['id']],
                            $context,
                        );
                    },
                );
            } catch (GoodsReceiptException $e) {
                return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return new Response('', Response::HTTP_OK);
    }

    #[Route(path: 'api/_action/pickware-erp/create-and-complete-goods-receipt', methods: 'PUT')]
    #[JsonValidation('create-and-complete-goods-receipt.schema.json')]
    public function createAndCompleteGoodsReceipt(Request $request, Context $context): Response
    {
        $goodsReceiptPayload = $request->get('goodsReceipt');
        $existingGoodsReceipt = $this->entityManager->findByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptPayload['id'],
            $context,
        );
        if ($existingGoodsReceipt === null) {
            try {
                $this->entityManager->runInTransactionWithRetry(
                    function() use ($context, $goodsReceiptPayload): void {
                        $this->goodsReceiptCreationService->createGoodsReceipt($goodsReceiptPayload, $context);
                        $this->goodsReceiptPriceCalculationService->recalculateGoodsReceipts(
                            [$goodsReceiptPayload['id']],
                            $context,
                        );
                        $this->goodsReceiptService->approve($goodsReceiptPayload['id'], $context);
                        $this->goodsReceiptService->startStocking($goodsReceiptPayload['id'], $context);
                        $this->goodsReceiptService->moveStockIntoWarehouse(
                            $goodsReceiptPayload['id'],
                            $goodsReceiptPayload['warehouseId'],
                            $context,
                        );
                        $this->goodsReceiptService->complete($goodsReceiptPayload['id'], $context);
                    },
                );
            } catch (GoodsReceiptException $e) {
                return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return new Response('', Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/goods-receipt/recalculate-goods-receipts', methods: 'POST')]
    #[JsonValidation(schemaFilePath: 'payload-recalculate-goods-receipts.schema.json')]
    public function recalculateGoodsReceipts(Request $request, Context $context): Response
    {
        $goodsReceiptIds = $request->get('goodsReceiptIds');
        $this->goodsReceiptPriceCalculationService->recalculateGoodsReceipts($goodsReceiptIds, $context);

        return new Response('', Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/goods-receipt/reassign-stock-destinations', methods: 'POST')]
    public function reassignGoodsReceiptStockDestinations(#[JsonParameterAsUuid] string $goodsReceiptId, Context $context): Response
    {
        try {
            $this->goodsReceiptStockDestinationAssignmentService->reassignGoodsReceiptStockDestinations($goodsReceiptId, $context);
        } catch (GoodsReceiptException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_OK);
    }

    /**
     * @deprecated tag:next-major The route _action/pickware-erp/goods-receipt/{goodsReceiptId}/document is deprecated
     * and will be removed. Use _action/pickware-erp/goods-receipt/{goodsReceiptId}/goods-receipt-stocking-list-document
     * instead.
     */
    #[Route(path: '/api/_action/pickware-erp/goods-receipt/{goodsReceiptId}/document', methods: 'GET')]
    #[Route(path: '/api/_action/pickware-erp/goods-receipt/{goodsReceiptId}/goods-receipt-stocking-list-document', methods: 'GET')]
    public function getGoodsReceiptStockingListDocument(
        Request $request,
        string $goodsReceiptId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^[0-9a-f]{32}$/'])]
        ?string $languageId,
        Context $context,
    ): Response {
        $languageId ??= Defaults::LANGUAGE_SYSTEM;

        $templateVariables = $this->goodsReceiptStockingListContentGenerator->generateForGoodsReceipt(
            $goodsReceiptId,
            $languageId,
            $context,
        );
        $renderedDocument = $this->goodsReceiptStockingListDocumentGenerator->generate($templateVariables, $languageId, $context);

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }

    #[Route(path: '/api/_action/pickware-erp/goods-receipt/{goodsReceiptId}/goods-receipt-note-document', methods: 'GET')]
    public function getGoodsReceiptNoteDocument(
        Request $request,
        string $goodsReceiptId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^[0-9a-f]{32}$/'])]
        ?string $languageId,
        Context $context,
    ): Response {
        $languageId ??= Defaults::LANGUAGE_SYSTEM;

        $templateVariables = $this->goodsReceiptNoteContentGenerator->generateForGoodsReceipt(
            $goodsReceiptId,
            $languageId,
            $context,
        );
        $renderedDocument = $this->goodsReceiptNoteDocumentGenerator->generate($templateVariables, $languageId, $context);

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }

    #[Route(path: '/api/_action/pickware-erp/goods-receipt/stock-and-complete-goods-receipt', methods: 'PUT')]
    #[JsonValidation('stock-and-complete-goods-receipt.schema.json')]
    public function stockAndCompleteGoodsReceipt(Request $request, Context $context): Response
    {
        $goodsReceiptId = $request->get('goodsReceiptId');
        $warehouseId = $request->get('warehouseId');

        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->findByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            ['state'],
        );
        if (!$goodsReceipt) {
            return GoodsReceiptError::goodsReceiptNotFound($goodsReceiptId)
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }
        if ($goodsReceipt->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_COMPLETED) {
            try {
                $this->entityManager->runInTransactionWithRetry(
                    function() use ($goodsReceipt, $context, $warehouseId, $goodsReceiptId): void {
                        if ($goodsReceipt->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_IN_PROGRESS) {
                            $this->goodsReceiptService->startStocking($goodsReceiptId, $context);
                        }

                        $this->goodsReceiptService->moveStockIntoWarehouse(
                            goodsReceiptId: $goodsReceiptId,
                            warehouseId: $warehouseId,
                            context: $context,
                        );
                        $this->goodsReceiptService->complete($goodsReceiptId, $context);
                    },
                );
            } catch (GoodsReceiptException $e) {
                return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return new Response('', Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/goods-receipt/approve-goods-receipt', methods: 'PUT')]
    #[JsonValidation('approve-goods-receipt.schema.json')]
    public function approveGoodsReceipt(Request $request, Context $context): Response
    {
        $goodsReceiptId = $request->get('goodsReceiptId');

        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->findByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            ['state'],
        );
        if (!$goodsReceipt) {
            return GoodsReceiptError::goodsReceiptNotFound($goodsReceiptId)
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }
        if ($goodsReceipt->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_APPROVED) {
            try {
                $this->goodsReceiptService->approve($goodsReceiptId, $context);
            } catch (GoodsReceiptException $e) {
                return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return new Response('', Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/goods-receipt/dispose-remaining-stock-and-complete-goods-receipt', methods: 'PUT')]
    public function disposeRemainingStockAndCompleteGoodsReceipt(
        #[JsonParameterAsUuid] string $goodsReceiptId,
        Context $context,
    ): Response {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->findByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            ['state'],
        );
        if (!$goodsReceipt) {
            return GoodsReceiptError::goodsReceiptNotFound($goodsReceiptId)
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        if ($goodsReceipt->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_COMPLETED) {
            try {
                $this->entityManager->runInTransactionWithRetry(
                    function() use ($context, $goodsReceiptId): void {
                        $this->goodsReceiptService->disposeRemainingStockInGoodsReceipt($goodsReceiptId, $context);
                        $this->goodsReceiptService->complete($goodsReceiptId, $context);
                    },
                );
            } catch (GoodsReceiptException $e) {
                return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return new Response('', Response::HTTP_OK);
    }

    /**
     * @param positive-int $newLineItemQuantity
     */
    #[Route(path: '/api/_action/pickware-erp/goods-receipt/split-line-item', methods: ['POST'])]
    public function splitGoodsReceiptLineItem(
        #[JsonParameterAsUuid] string $lineItemId,
        #[JsonParameter(validations: [new Positive()])] int $newLineItemQuantity,
        Context $context,
    ): Response {
        try {
            $this->goodsReceiptService->splitGoodsReceiptLineItem($lineItemId, $newLineItemQuantity, $context);
        } catch (GoodsReceiptException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/pickware-erp/create-goods-receipt-for-return-order', methods: 'PUT')]
    #[JsonValidation('create-goods-receipt-for-return-order.schema.json')]
    public function createGoodsReceiptForReturnOrder(
        #[JsonParameter] array $goodsReceiptPayload,
        #[JsonParameter] array $disposeQuantities,
        Context $context,
    ): Response {
        $returnOrderId = $goodsReceiptPayload['returnOrders'][0]['id'];
        $goodsReceiptId = $goodsReceiptPayload['id'];
        $disposeQuantities = ProductQuantityImmutableCollection::fromArray($disposeQuantities);

        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($context, $goodsReceiptPayload, $goodsReceiptId, $disposeQuantities, $returnOrderId): void {
                    $this->goodsReceiptCreationService->createGoodsReceipt($goodsReceiptPayload, $context);
                    // Approving a goods receipt will move the stock into the goods receipt
                    $this->goodsReceiptService->approve($goodsReceiptId, $context);
                    $this->stockMovementService->moveStock(
                        stockMovements: $disposeQuantities
                            ->createStockMovements(
                                source: StockLocationReference::goodsReceipt($goodsReceiptId),
                                destination: StockLocationReference::unknown(),
                            )
                            ->asArray(),
                        context: $context,
                    );
                },
            );
        } catch (GoodsReceiptException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/get-goods-receipt-line-items-total-quantity', methods: 'POST')]
    public function getGoodsReceiptLineItemsTotalQuantity(
        #[JsonParameterAsUuid] string $goodsReceiptId,
    ): Response {
        $totalQuantity = (int) $this->connection->fetchOne(
            'SELECT SUM(`quantity`) FROM `pickware_erp_goods_receipt_line_item` WHERE `goods_receipt_id` = :goodsReceiptId',
            ['goodsReceiptId' => hex2bin($goodsReceiptId)],
        );

        return new JsonResponse(['totalQuantity' => $totalQuantity], Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/get-stock-valuation-report-id-for-goods-receipt', methods: 'POST')]
    public function getStockValuationReportIdForGoodsReceipt(
        #[JsonParameterAsUuid] string $goodsReceiptId,
        Context $context,
    ): Response {
        $stockValuationReportId = $this->goodsReceiptService->getStockValuationReportIdForGoodsReceipt($goodsReceiptId, $context);

        return new JsonResponse(['stockValuationReportId' => $stockValuationReportId], Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/update-goods-receipt-warehouse', methods: ['POST'])]
    public function updateGoodsReceiptWarehouse(
        #[JsonParameterAsUuid] string $goodsReceiptId,
        #[JsonParameterAsUuid] string $warehouseId,
        Context $context,
    ): Response {
        try {
            $this->goodsReceiptService->updateGoodsReceiptWarehouse($goodsReceiptId, $warehouseId, $context);
        } catch (GoodsReceiptException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_OK);
    }

    /**
     * @param GoodsReceiptLineItemUpdate $payload
     */
    #[Route(path: '/api/_action/pickware-erp/update-goods-receipt-line-item', methods: ['POST'])]
    #[JsonValidation('update-goods-receipt-line-item.schema.json')]
    public function updateGoodsReceiptLineItem(
        #[JsonParameterAsUuid] string $lineItemId,
        #[JsonParameter] array $payload,
        Context $context,
    ): Response {
        try {
            $this->goodsReceiptUpdateService->updateGoodsReceiptLineItem($lineItemId, $payload, $context);
        } catch (GoodsReceiptException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_OK);
    }
}
