<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptCreationService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptService;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderCreationService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderLineItemService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderPriceCalculationService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsArrayOfUuids;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class ReturnOrderController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ReturnOrderService $returnOrderService,
        private readonly ReturnOrderLineItemService $returnOrderLineItemService,
        private readonly ReturnOrderCreationService $returnOrderCreationService,
        private readonly ReturnOrderPriceCalculationService $returnOrderPriceCalculationService,
        private readonly EntityResponseService $entityResponseService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly GoodsReceiptCreationService $goodsReceiptCreationService,
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly StateTransitionService $stateTransitionService,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/get-maximum-quantities-for-new-return-order-line-items',
        name: 'api.action.pickware-erp.get-maximum-quantities-for-new-return-order-line-items',
        methods: ['POST'],
    )]
    public function getMaximumQuantitiesForNewReturnOrderLineItems(
        #[JsonParameterAsArrayOfUuids] array $orderIds,
        Context $context,
    ): Response {
        return new JsonResponse(
            $this->returnOrderLineItemService->getMaximumQuantitiesForNewReturnOrderLineItems($orderIds, $context),
            Response::HTTP_OK,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-create-completed-return-orders.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/create-completed-return-orders',
        name: 'api.action.pickware-erp.create-completed-return-orders',
        methods: ['POST'],
    )]
    public function createCompletedReturnOrders(Context $context, Request $request): Response
    {
        $returnOrderPayloads = $request->get('returnOrders');

        // Create stock adjustments to restock all order line item quantities to the given warehouse
        $stockAdjustmentsByReturnOrderId = [];
        $warehouseIdsByReturnOrderIds = [];
        foreach ($returnOrderPayloads as $returnOrderPayload) {
            $stockAdjustmentsByReturnOrderId[$returnOrderPayload['id']] = [];
            $warehouseIdsByReturnOrderIds[$returnOrderPayload['id']] = $returnOrderPayload['warehouseId'];
            foreach ($returnOrderPayload['lineItems'] as $returnOrderLineItemPayload) {
                if ($returnOrderLineItemPayload['type'] !== ReturnOrderLineItemDefinition::TYPE_PRODUCT) {
                    continue;
                }
                $stockAdjustmentsByReturnOrderId[$returnOrderPayload['id']][] = [
                    'productId' => $returnOrderLineItemPayload['productId'],
                    'dispose' => 0,
                    'restock' => $returnOrderLineItemPayload['quantity'],
                ];
            }
        }

        $this->entityManager->runInTransactionWithRetry(
            function() use (
                $context,
                $returnOrderPayloads,
                $stockAdjustmentsByReturnOrderId,
                $warehouseIdsByReturnOrderIds,
            ): void {
                $returnOrderIds = array_column($returnOrderPayloads, 'id');
                $this->returnOrderCreationService->createReturnOrders($returnOrderPayloads, $context);
                $this->returnOrderPriceCalculationService->recalculateReturnOrders(
                    array_map(fn(array $returnOrderPayload) => $returnOrderPayload['id'], $returnOrderPayloads),
                    $context,
                );
                $this->returnOrderService->approveReturnOrders($returnOrderIds, $context);

                if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
                    $goodsReceiptPayloads = $this->goodsReceiptCreationService->createGoodsReceiptPayloadsFromReturnOrder(
                        $returnOrderIds,
                        $context,
                    );

                    foreach ($goodsReceiptPayloads as $goodsReceiptPayload) {
                        $this->goodsReceiptCreationService->createGoodsReceipt($goodsReceiptPayload, $context);
                        $this->goodsReceiptService->approve($goodsReceiptPayload['id'], $context);
                        $this->goodsReceiptService->startStocking($goodsReceiptPayload['id'], $context);
                        $this->goodsReceiptService->moveStockIntoWarehouse(
                            $goodsReceiptPayload['id'],
                            $goodsReceiptPayload['warehouseId'],
                            $context,
                        );
                        $this->goodsReceiptService->complete($goodsReceiptPayload['id'], $context);
                    }
                } else {
                    foreach ($returnOrderIds as $returnOrderId) {
                        $this->returnOrderService->markReturnOrderAsReceived($returnOrderId, $context);
                    }

                    $this->returnOrderService->moveStockIntoReturnOrders(
                        $this->returnOrderService->getProductQuantitiesByReturnOrderId($returnOrderIds, $context),
                        $context,
                    );
                    $this->returnOrderService->moveStockFromReturnOrders(
                        $stockAdjustmentsByReturnOrderId,
                        $warehouseIdsByReturnOrderIds,
                        $context,
                    );
                }

                $this->returnOrderService->completeReturnOrders($returnOrderIds, $context);
            },
        );

        return $this->entityResponseService->makeEntityListingResponse(
            ReturnOrderDefinition::class,
            array_column($returnOrderPayloads, 'id'),
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-request-and-approve-return-order.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/request-and-approve-return-order',
        name: 'api.action.pickware-erp.request-and-approve-return-order',
        methods: ['POST'],
    )]
    public function requestAndApproveReturnOrder(Context $context, Request $request): Response
    {
        $returnOrderPayload = $request->get('returnOrder');

        $this->entityManager->runInTransactionWithRetry(
            function() use ($context, $returnOrderPayload): void {
                $this->returnOrderCreationService->createReturnOrders([$returnOrderPayload], $context);
                $this->returnOrderPriceCalculationService->recalculateReturnOrders(
                    [$returnOrderPayload['id']],
                    $context,
                );
                $this->returnOrderService->approveReturnOrders([$returnOrderPayload['id']], $context);
            },
        );

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderPayload['id'],
            $context,
        );
    }

    #[Route(
        path: '/api/_action/pickware-erp/approve-return-order',
        name: 'api.action.pickware-erp.approve-return-order',
        methods: ['POST'],
    )]
    public function approveReturnOrder(#[JsonParameterAsUuid] string $returnOrderId, Context $context): Response
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($context, $returnOrderId): void {
                $this->returnOrderService->approveReturnOrders([$returnOrderId], $context);
            },
        );

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-receive-return-order.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/receive-return-order',
        name: 'api.action.pickware-erp.receive-return-order',
        methods: ['POST'],
    )]
    public function receiveReturnOrder(Context $context, Request $request): Response
    {
        $returnOrderId = $request->get('returnOrderId');

        $this->returnOrderService->markReturnOrderAsReceived($returnOrderId, $context);

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-complete-return-order.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/complete-return-order',
        name: 'api.action.pickware-erp.complete-return-order',
        methods: ['POST'],
    )]
    public function completeReturnOrder(Context $context, Request $request): Response
    {
        $returnOrderId = $request->get('returnOrderId');
        $stockAdjustments = $request->get('stockAdjustments');

        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            $this->returnOrderService->completeReturnOrders([$returnOrderId], $context);
        } else {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($returnOrderId, $stockAdjustments, $context): void {
                    /** @var ReturnOrderEntity $returnOrder */
                    $returnOrder = $this->entityManager->getByPrimaryKey(
                        ReturnOrderDefinition::class,
                        $returnOrderId,
                        $context,
                    );
                    $this->returnOrderService->moveStockIntoReturnOrders(
                        $this->returnOrderService->getProductQuantitiesByReturnOrderId([$returnOrderId], $context),
                        $context,
                    );
                    $this->returnOrderService->completeReturnOrders([$returnOrderId], $context);
                    $this->returnOrderService->moveStockFromReturnOrders(
                        [$returnOrderId => $stockAdjustments],
                        [$returnOrderId => $returnOrder->getWarehouseId()],
                        $context,
                    );
                },
            );
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
        );
    }

    #[Route(
        path: '/api/_action/pickware-erp/cancel-return-order',
        name: 'api.action.pickware-erp.cancel-return-order',
        methods: ['POST'],
    )]
    public function cancelReturnOrder(
        #[JsonParameterAsUuid] string $returnOrderId,
        Context $context,
    ): Response {
        // The service is called in a transaction, so that all data is rolled back if an error is thrown during the
        // state transition or in subscribers, which listens to the event dispatched in the service.
        // This is crucial to ensure that return orders remain in a consistent state with any other plugin.
        $this->entityManager->runInTransactionWithRetry(
            fn() => $this->returnOrderService->cancelReturnOrder($returnOrderId, $context),
        );

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
        );
    }

    #[Route(
        path: '/api/_action/pickware-erp/decline-return-order',
        name: 'api.action.pickware-erp.decline-return-order',
        methods: ['POST'],
    )]
    public function declineReturnOrder(
        #[JsonParameterAsUuid] string $returnOrderId,
        Context $context,
    ): Response {
        // The service is called in a transaction, so that all data is rolled back if an error is thrown during the
        // state transition or in subscribers, which listens to the event dispatched in the service.
        // This is crucial to ensure that return orders remain in a consistent state with any other plugin.
        $this->entityManager->runInTransactionWithRetry(
            fn() => $this->returnOrderService->declineReturnOrder($returnOrderId, $context),
        );

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-recalculate-return-order.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/recalculate-return-orders',
        name: 'api.action.pickware-erp.recalculate-return-orders',
        methods: ['POST'],
    )]
    public function recalculateReturnOrders(Context $context, Request $request): Response
    {
        $returnOrderIds = $request->get('returnOrderIds');

        $this->entityManager->runInTransactionWithRetry(
            function() use ($returnOrderIds, $context): void {
                $this->returnOrderPriceCalculationService->recalculateReturnOrders(
                    $returnOrderIds,
                    $context,
                );
            },
        );

        return $this->entityResponseService->makeEntityListingResponse(
            ReturnOrderDefinition::class,
            $returnOrderIds,
            $context,
        );
    }

    #[Route(
        path: '/api/_action/pickware-erp/mark-return-order-as-received',
        methods: ['POST'],
    )]
    public function markReturnOrderAsReceived(
        #[JsonParameter] string $returnOrderId,
        Context $context,
    ): Response {
        $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
            new Transition(
                ReturnOrderDefinition::ENTITY_NAME,
                $returnOrderId,
                ReturnOrderStateMachine::TRANSITION_RECEIVE,
                'stateId',
            ),
            $context,
        );

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
        );
    }
}
