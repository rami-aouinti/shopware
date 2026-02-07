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
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Document\GoodsReceiptStockingListContentGenerator;
use Pickware\PickwareErpStarter\GoodsReceipt\Document\GoodsReceiptStockingListDocumentGenerator;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptCreationService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptService;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptType;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Document\ReturnOrderStockingListContentGenerator;
use Pickware\PickwareErpStarter\ReturnOrder\Document\ReturnOrderStockingListDocumentGenerator;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Is being used for backwards compatibility to older pickware-wms plugins. This enables them to create goods receipts
 * when receiving a return order.
 */
class LegacyReturnOrderService
{
    public function __construct(
        private readonly ReturnOrderService $returnOrderService,
        private readonly EntityManager $entityManager,
        private readonly ReturnOrderPriceCalculationService $returnOrderPriceCalculationService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly GoodsReceiptCreationService $goodsReceiptCreationService,
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly ReturnOrderStockingListContentGenerator $returnOrderStockingListContentGenerator,
        private readonly ReturnOrderStockingListDocumentGenerator $returnOrderStockingListDocumentGenerator,
        private readonly GoodsReceiptStockingListContentGenerator $goodsReceiptStockingListContentGenerator,
        private readonly GoodsReceiptStockingListDocumentGenerator $goodsReceiptStockingListDocumentGenerator,
    ) {}

    /**
     * @param ImmutableCollection<ReturnedProduct>|ProductQuantityImmutableCollection $returnedProducts
     */
    public function receiveReturnOrder(
        string $returnOrderId,
        ImmutableCollection|ProductQuantityImmutableCollection $returnedProducts,
        Context $context,
    ): void {
        if (
            !($returnedProducts instanceof ProductQuantityImmutableCollection)
            && $this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)
        ) {
            trigger_error(
                'The $returnedProducts parameter of ' . __METHOD__ . ' should be an instance of ' .
                ProductQuantityImmutableCollection::class . '. Will be enforced in 4.0.0',
                E_USER_DEPRECATED,
            );
            $returnedProducts = $returnedProducts->map(fn(ReturnedProduct $returnedProduct) => new ProductQuantity(
                $returnedProduct->getProductId(),
                $returnedProduct->getQuantity(),
            ), ProductQuantityImmutableCollection::class);
        }
        $this->entityManager->runInTransactionWithRetry(
            function() use ($returnOrderId, $returnedProducts, $context): void {
                if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
                    $this->returnOrderPriceCalculationService->recalculateReturnOrders(
                        [$returnOrderId],
                        $context,
                    );

                    /** @var ReturnOrderEntity $returnOrder */
                    $returnOrder = $this->entityManager->getByPrimaryKey(
                        ReturnOrderDefinition::class,
                        $returnOrderId,
                        $context,
                        ['order.orderCustomer'],
                    );
                    $goodsReceiptId = Uuid::randomHex();

                    // Create the goods receipt in System scope because an older POS plugin might not have sufficient
                    // permissions yet.
                    $context->scope(Context::SYSTEM_SCOPE, function(Context $context) use (
                        $returnedProducts,
                        $returnOrderId,
                        $returnOrder,
                        $goodsReceiptId
                    ): void {
                        $this->goodsReceiptCreationService->createGoodsReceipt([
                            'id' => $goodsReceiptId,
                            'type' => GoodsReceiptType::Customer,
                            'customerId' => $returnOrder->getOrder()->getOrderCustomer()->getCustomerId(),
                            'returnOrders' => [['id' => $returnOrderId]],
                            'warehouseId' => $returnOrder->getWarehouseId(),
                            'lineItems' => $returnedProducts
                                ->groupByProductId()
                                ->map(
                                    fn(ProductQuantity $productQuantity) => [
                                        'productId' => $productQuantity->getProductId(),
                                        'quantity' => $productQuantity->getQuantity(),
                                        'returnOrderId' => $returnOrderId,
                                    ],
                                )->asArray(),
                        ], $context);
                    });

                    // Approve moves stock into the goods receipt
                    $this->goodsReceiptService->approve($goodsReceiptId, $context);
                } else {
                    $this->returnOrderService->setProductsOfReturnOrder(
                        returnOrderId: $returnOrderId,
                        returnedProducts: $returnedProducts,
                        context: $context,
                    );

                    $this->returnOrderPriceCalculationService->recalculateReturnOrders(
                        [$returnOrderId],
                        $context,
                    );

                    $this->returnOrderService->markReturnOrderAsReceived($returnOrderId, $context);
                }
            },
        );
    }

    /**
     * Completes the return order and restocks everything (all quantities of restockable line items) into the given
     * warehouse
     */
    public function completeReturnOrderWithFullRestock(
        string $returnOrderId,
        string $warehouseId,
        Context $context,
    ): void {
        $this->entityManager->runInTransactionWithRetry(function() use ($returnOrderId, $warehouseId, $context): void {
            if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
                // We fetch the goods receipt that is associated with this return order.
                // Currently, a return order can only be associated with one goods receipt,
                // this is why it suffices to complete that one here.
                /** @var GoodsReceiptEntity $goodsReceipt */
                $goodsReceipt = $this->entityManager->getFirstBy(
                    GoodsReceiptDefinition::class,
                    ['returnOrders.id' => $returnOrderId],
                    [new FieldSorting('createdAt', FieldSorting::DESCENDING)],
                    $context,
                    ['returnOrders'],
                );
                $this->goodsReceiptService->startStocking($goodsReceipt->getId(), $context);
                $this->goodsReceiptService->moveStockIntoWarehouse(
                    $goodsReceipt->getId(),
                    $warehouseId,
                    $context,
                );
                $this->goodsReceiptService->complete($goodsReceipt->getId(), $context);
            } else {
                $productQuantities = $this->returnOrderService->getProductQuantitiesByReturnOrderId(
                    [$returnOrderId],
                    $context,
                );
                $stockAdjustments = [];
                foreach ($productQuantities[$returnOrderId] as $productId => $quantity) {
                    $stockAdjustments[] = [
                        'productId' => $productId,
                        'restock' => $quantity,
                        'dispose' => 0,
                    ];
                }

                $this->returnOrderService->moveStockIntoReturnOrders($productQuantities, $context);
                $this->returnOrderService->moveStockFromReturnOrders(
                    stockAdjustmentsByReturnOrderId: [$returnOrderId => $stockAdjustments],
                    warehouseIdsByReturnOrderId: [$returnOrderId => $warehouseId],
                    context: $context,
                );
            }

            // The return order might have been restocked into a different warehouse than the one it was created
            // with, so we update the warehouse here.
            $this->entityManager->update(
                ReturnOrderDefinition::class,
                [
                    [
                        'id' => $returnOrderId,
                        'warehouseId' => $warehouseId,
                    ],
                ],
                $context,
            );
            $this->returnOrderService->completeReturnOrders([$returnOrderId], $context);
        });
    }

    public function generateReturnOrderStockingListDocument(
        string $returnOrderId,
        string $languageId,
        Context $context,
    ): RenderedDocument {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            // We fetch the goods receipt that is associated with this return order.
            // Currently, a return order can only be associated with one goods receipt,
            // this is why it suffices to complete that one here.
            /** @var GoodsReceiptEntity $goodsReceipt */
            $goodsReceipt = $this->entityManager->getFirstBy(
                GoodsReceiptDefinition::class,
                ['returnOrders.id' => $returnOrderId],
                [new FieldSorting('createdAt', FieldSorting::DESCENDING)],
                $context,
                ['returnOrders'],
            );

            $templateVariables = $this->goodsReceiptStockingListContentGenerator->generateForGoodsReceipt(
                $goodsReceipt->getId(),
                $languageId,
                $context,
            );

            return $this->goodsReceiptStockingListDocumentGenerator->generate(
                $templateVariables,
                $languageId,
                $context,
            );
        }
        $templateVariables = $this->returnOrderStockingListContentGenerator->generateForReturnOrder(
            $returnOrderId,
            $languageId,
            $context,
        );

        return $this->returnOrderStockingListDocumentGenerator->generate(
            $templateVariables,
            $languageId,
            $context,
        );
    }
}
