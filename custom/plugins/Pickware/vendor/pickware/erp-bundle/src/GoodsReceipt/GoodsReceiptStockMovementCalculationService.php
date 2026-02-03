<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Shopware\Core\Framework\Context;

class GoodsReceiptStockMovementCalculationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Moves the given stock quantities into the goods receipt. The corresponding order is used as stock source for as
     * much stock as possible. If the stock in the order does not suffice, stock from the unknown stock location is
     * moved into the goods receipt until the given stock quantity is achieved.
     *
     * @param ImmutableCollection<GoodsReceiptStockItem> $goodsReceiptStockItems
     * @return StockMovement[]
     */
    public function calculateStockMovementsFromOrdersIntoGoodsReceipt(
        string $goodsReceiptId,
        ImmutableCollection $goodsReceiptStockItems,
        Context $context,
    ): array {
        $orderIds = $goodsReceiptStockItems
            ->compactMap(fn(GoodsReceiptStockItem $item) => $item->getOrderId())
            ->deduplicate();
        $productIds = $goodsReceiptStockItems
            ->map(fn(GoodsReceiptStockItem $item) => $item->getProductId())
            ->deduplicate();
        /** @var StockCollection $orderStocks */
        $orderStocks = $this->entityManager->findBy(
            StockDefinition::class,
            [
                'productId' => $productIds->asArray(),
                'locationType.technicalName' => LocationTypeDefinition::TECHNICAL_NAME_ORDER,
                'orderId' => $orderIds->asArray(),
            ],
            $context,
            [
                'batchMappings',
            ],
        );

        $stockMovements = [];
        foreach ($goodsReceiptStockItems as $item) {
            $targetQuantity = $item->getQuantity();
            $stockInOrderForProduct = 0;
            $orderStock = $orderStocks->filter(
                fn(StockEntity $stock) => $stock->getOrderId() === $item->getOrderId()
                    && $stock->getProductId() === $item->getProductId(),
            )->first();
            if ($orderStock !== null) {
                if ($item->getBatchId() === null) {
                    $stockInOrderForProduct = $orderStock->getQuantity();
                } else {
                    $orderBatches = $orderStock->getBatchMappings()->asBatchCountingMap();
                    // We can move as much stock from the order as there is for the batch,
                    $stockInOrderForProduct = $orderBatches->get($item->getBatchId());
                    // plus any stock without batch information.
                    $stockInOrderForProduct += max(0, $orderStock->getQuantity() - $orderBatches->getTotalCount());
                }
            }

            if ($stockInOrderForProduct < $targetQuantity) {
                // If there is not enough stock in the order, we move all existing stock from the order
                // to the goods receipt and the remaining difference from unknown.
                $returnQuantityFromOrder = $stockInOrderForProduct;
                $returnQuantityFromUnknown = $targetQuantity - $stockInOrderForProduct;
            } else {
                // Move the total target quantity from the order to the goods receipt otherwise.
                $returnQuantityFromOrder = $targetQuantity;
                $returnQuantityFromUnknown = 0;
            }

            if ($returnQuantityFromUnknown > 0) {
                $stockMovements[] = $item->createStockMovementWithQuantity(
                    source: StockLocationReference::unknown(),
                    destination: StockLocationReference::goodsReceipt($goodsReceiptId),
                    quantity: $returnQuantityFromUnknown,
                    context: $context,
                );
            }
            if ($returnQuantityFromOrder > 0) {
                $stockMovements[] = $item->createStockMovementWithQuantity(
                    source: StockLocationReference::order($item->getOrderId()),
                    destination: StockLocationReference::goodsReceipt($goodsReceiptId),
                    quantity: $returnQuantityFromOrder,
                    context: $context,
                );
            }
        }

        return $stockMovements;
    }
}
