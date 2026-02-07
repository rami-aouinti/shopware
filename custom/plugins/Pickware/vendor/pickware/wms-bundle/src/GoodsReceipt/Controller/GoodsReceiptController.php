<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\GoodsReceipt\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptCreationService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptException;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptPriceCalculationService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareWms\GoodsReceipt\FeatureFlags\AutomaticGoodsReceiptApprovalProdFeatureFlag;
use Pickware\PickwareWms\GoodsReceipt\GoodsReceiptError;
use Pickware\PickwareWms\GoodsReceipt\GoodsReceiptReturnOrderReasonAssignmentService;
use Pickware\PickwareWms\PickwareWmsBundle;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class GoodsReceiptController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntityResponseService $entityResponseService,
        private readonly SystemConfigService $systemConfigService,
        private readonly GoodsReceiptCreationService $goodsReceiptCreationService,
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly GoodsReceiptReturnOrderReasonAssignmentService $goodsReceiptReturnOrderReasonAssignmentService,
        private readonly GoodsReceiptPriceCalculationService $goodsReceiptPriceCalculationService,
        private readonly StockMovementService $stockMovementService,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    #[Route(path: '/api/_action/pickware-wms/create-and-approve-goods-receipt', methods: 'PUT')]
    #[JsonValidation('create-and-approve-goods-receipt.schema.json')]
    public function createAndApproveGoodsReceipt(Request $request, Context $context): Response
    {
        $goodsReceiptPayload = $request->get('goodsReceipt');
        // In case the goods receipt is created for a return order, we do not want to create the goods receipt right
        // away. Instead, we want to create the goods receipt first without a source and then assign the return order
        // afterward. This allows `pickware-erp-starter` to find or create corresponding return orders.
        $returnOrdersPayload = $goodsReceiptPayload['returnOrders'] ?? [];
        unset($goodsReceiptPayload['returnOrders']);

        $goodsReceipt = $this->entityManager->findByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptPayload['id'],
            $context,
        );
        if (!$goodsReceipt) {
            // This is an idempotency check. If a goods receipt with the same ID already exist we assume this action
            // has been executed already and just return the goods receipt.
            try {
                $this->entityManager->runInTransactionWithRetry(
                    function() use ($context, $goodsReceiptPayload, $returnOrdersPayload): void {
                        $this->goodsReceiptCreationService->createGoodsReceipt($goodsReceiptPayload, $context);
                        if (!empty($returnOrdersPayload)) {
                            $this->goodsReceiptService->mapReturnOrdersToGoodsReceipt(
                                goodsReceiptId: $goodsReceiptPayload['id'],
                                returnOrders: $returnOrdersPayload,
                                context: $context,
                            );
                        }
                        $this->goodsReceiptService->reassignLineItemsToSources($goodsReceiptPayload['id'], $context);
                        if (!empty($returnOrdersPayload)) {
                            $this->goodsReceiptReturnOrderReasonAssignmentService->assignReturnReasonsForGoodsReceiptReturns(
                                $goodsReceiptPayload['id'],
                                array_reduce(
                                    $goodsReceiptPayload['lineItems'],
                                    function(array $productReturnReasons, array $lineItem): array {
                                        if (!empty($lineItem['returnReason'])) {
                                            $productReturnReasons[$lineItem['productId']] = $lineItem['returnReason'];
                                        }

                                        return $productReturnReasons;
                                    },
                                    [],
                                ),
                                $context,
                            );
                        }
                        $this->goodsReceiptPriceCalculationService->recalculateGoodsReceipts(
                            [$goodsReceiptPayload['id']],
                            $context,
                        );
                        if ($this->featureFlagService->isActive(AutomaticGoodsReceiptApprovalProdFeatureFlag::NAME)) {
                            $automaticallyApproveGoodsReceipts = $this->systemConfigService
                                ->get(PickwareWmsBundle::GLOBAL_PLUGIN_CONFIG_DOMAIN . '.automaticallyApproveGoodsReceipts') ?? true;
                        } else {
                            $automaticallyApproveGoodsReceipts = true;
                        }
                        // A goods receipt for a return order must always be approved
                        if ($automaticallyApproveGoodsReceipts || !empty($returnOrdersPayload)) {
                            // Approving a goods receipt will move the stock into the goods receipt
                            $this->goodsReceiptService->approve($goodsReceiptPayload['id'], $context);
                            $dispositionStockMovements = [];
                            foreach ($goodsReceiptPayload['lineItems'] ?? [] as $lineItem) {
                                if (($lineItem['quantityToDispose'] ?? 0) === 0) {
                                    continue;
                                }

                                $stockMovementPayload = [
                                    'productId' => $lineItem['productId'],
                                    'quantity' => $lineItem['quantityToDispose'],
                                    'source' => StockLocationReference::goodsReceipt($goodsReceiptPayload['id']),
                                    'destination' => StockLocationReference::unknown(),
                                ];
                                if ($lineItem['batchId'] ?? null) {
                                    $stockMovementPayload['batches'] = new CountingMap([
                                        $lineItem['batchId'] => $lineItem['quantityToDispose'],
                                    ]);
                                }
                                $dispositionStockMovements[] = StockMovement::create($stockMovementPayload);
                            }
                            if (count($dispositionStockMovements) > 0) {
                                $this->stockMovementService->moveStock(
                                    stockMovements: $dispositionStockMovements,
                                    context: $context,
                                );
                            }
                            /** @var GoodsReceiptEntity $goodsReceipt */
                            $goodsReceipt = $this->entityManager->getByPrimaryKey(
                                GoodsReceiptDefinition::class,
                                $goodsReceiptPayload['id'],
                                $context,
                                ['stocks'],
                            );
                            if ($goodsReceipt->getStocks()->count() === 0) {
                                if (
                                    !(new GoodsReceiptStateMachine())->allowsTransitionFromStateToState(
                                        GoodsReceiptStateMachine::STATE_APPROVED,
                                        GoodsReceiptStateMachine::STATE_COMPLETED,
                                    )
                                ) {
                                    // This check ensures backwards compatibility with pickware-erp-starter versions that
                                    // do not contain the required state transition. It can be removed when pickware-wms
                                    // requires at least a pickware-erp-starter version greater than v4.6.1.
                                    $this->goodsReceiptService->startStocking($goodsReceiptPayload['id'], $context);
                                }
                                $this->goodsReceiptService->complete($goodsReceiptPayload['id'], $context);
                            }
                        }
                    },
                );
            } catch (GoodsReceiptException $e) {
                return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            GoodsReceiptDefinition::class,
            $goodsReceiptPayload['id'],
            $context,
        );
    }

    #[Route(path: '/api/_action/pickware-wms/stock-and-complete-goods-receipt', methods: 'PUT')]
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
                    function() use ($context, $warehouseId, $goodsReceiptId): void {
                        // This check ensures backwards compatibility with pickware-erp-versions < 3.15.1
                        if (method_exists($this->goodsReceiptService, 'startStocking')) {
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

        return $this->entityResponseService->makeEntityDetailResponse(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
        );
    }
}
