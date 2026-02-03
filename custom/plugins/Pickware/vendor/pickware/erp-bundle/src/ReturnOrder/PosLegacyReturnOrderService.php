<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptCreationService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptService;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Is being used for backwards compatibility to older pickware-pos plugins. This enables them to create goods receipts
 * when receiving a return order.
 */
class PosLegacyReturnOrderService
{
    public function __construct(
        private readonly ReturnOrderService $returnOrderService,
        private readonly EntityManager $entityManager,
        private readonly StockMovementService $stockMovementService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly GoodsReceiptCreationService $goodsReceiptCreationService,
        private readonly GoodsReceiptService $goodsReceiptService,
    ) {}

    public function receiveReturnOrder(string $returnOrderId, Context $context): void
    {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            $goodsReceiptPayload = $this->goodsReceiptCreationService->createGoodsReceiptPayloadsFromReturnOrder(
                [$returnOrderId],
                $context,
            )[0];

            // Create and approve the goods receipt in the System scope for forwards compatibility, in case ERP
            // performs additional actions which the POS user does not have permissions for.
            $context->scope(Context::SYSTEM_SCOPE, function(Context $context) use ($goodsReceiptPayload): void {
                $this->goodsReceiptCreationService->createGoodsReceipt($goodsReceiptPayload, $context);
                $this->goodsReceiptService->approve($goodsReceiptPayload['id'], $context);
            });
        } else {
            $this->returnOrderService->markReturnOrderAsReceived($returnOrderId, $context);
        }
    }

    /**
     * Restocks the stock in a return order on the given location of the $restockedItems. The remaining stock in the
     * return order is discarded. The return order is finally marked as completed.
     */
    public function completeReturnOrder(
        string $returnOrderId,
        ProductQuantityLocationImmutableCollection $itemsToRestock,
        Context $context,
    ): void {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($returnOrderId, $itemsToRestock, $context): void {
                if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
                    /** @var GoodsReceiptEntity $goodsReceipt */
                    $goodsReceipt = $this->entityManager->getOneBy(GoodsReceiptDefinition::class, ['returnOrders.id' => $returnOrderId], $context);

                    $this->goodsReceiptService->startStocking($goodsReceipt->getId(), $context);

                    // Restock items into passed stock locations
                    $this->stockMovementService->moveStock(
                        $itemsToRestock->createStockMovementsWithSource(
                            StockLocationReference::goodsReceipt($goodsReceipt->getId()),
                        ),
                        $context,
                    );

                    $this->goodsReceiptService->disposeRemainingStockInGoodsReceipt($goodsReceipt->getId(), $context);

                    $this->goodsReceiptService->complete($goodsReceipt->getId(), $context);
                } else {
                    $this->entityManager->lockPessimistically(
                        StockDefinition::class,
                        ['returnOrderId' => $returnOrderId],
                        $context,
                    );

                    $this->returnOrderService->moveStockIntoReturnOrders(
                        $this->returnOrderService->getProductQuantitiesByReturnOrderId([$returnOrderId], $context),
                        $context,
                    );

                    // Restock items into passed stock locations
                    $this->stockMovementService->moveStock(
                        $itemsToRestock->createStockMovementsWithSource(
                            StockLocationReference::returnOrder($returnOrderId),
                        ),
                        $context,
                    );

                    // Discard remaining stock in return order
                    /** @var StockCollection $stocksInReturnOrder */
                    $stocksInReturnOrder = $this->entityManager->findBy(
                        StockDefinition::class,
                        ['returnOrderId' => $returnOrderId],
                        $context,
                    );
                    $stockMovements = $stocksInReturnOrder
                        ->getProductQuantities()
                        ->map(fn(ProductQuantity $productQuantity) => StockMovement::create([
                            'id' => Uuid::randomHex(),
                            'productId' => $productQuantity->getProductId(),
                            'quantity' => $productQuantity->getQuantity(),
                            'source' => StockLocationReference::returnOrder($returnOrderId),
                            'destination' => StockLocationReference::specialStockLocation(
                                SpecialStockLocationDefinition::TECHNICAL_NAME_UNKNOWN,
                            ),
                        ]));
                    $this->stockMovementService->moveStock($stockMovements->asArray(), $context);
                }

                $this->returnOrderService->completeReturnOrders([$returnOrderId], $context);
            },
        );
    }
}
