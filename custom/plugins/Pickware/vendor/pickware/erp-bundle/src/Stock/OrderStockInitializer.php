<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class OrderStockInitializer
{
    public const ORDER_STOCK_RELEVANT_LINE_ITEM_TYPES = [
        LineItem::PRODUCT_LINE_ITEM_TYPE,
    ];

    private EntityManager $entityManager;
    private StockMovementService $stockMovementService;

    public function __construct(
        EntityManager $entityManager,
        StockMovementService $stockMovementService,
    ) {
        $this->entityManager = $entityManager;
        $this->stockMovementService = $stockMovementService;
    }

    /**
     * "Lazy" initializes an order when NO stock was moved into the order before at all. We consider these orders
     * "shipped before ERP was installed/active".
     */
    public function initializeOrderIfNecessary(string $orderId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($orderId, $context): void {
            $this->lockProductStocks($orderId, $context);
            $order = $this->getOrder($orderId, $context, [
                'lineItems',
                'deliveries',
                'pickwareErpDestinationStockMovements',
            ]);

            if ($order->getExtensions()['pickwareErpDestinationStockMovements']->count() > 0) {
                // If any stock was already moved into the order we do not initialize it anymore.
                return;
            }

            $this->initializeOrder($order, $context);
        });
    }

    /**
     * Initializes an order by moving all stock into it from an external stock location and therefore without changing
     * the internal stock.
     */
    private function initializeOrder(OrderEntity $order, Context $context): void
    {
        $stockMovements = [];
        foreach ($order->getLineItems() as $orderLineItem) {
            /** @var OrderLineItemEntity $orderLineItem */
            if (!in_array($orderLineItem->getType(), self::ORDER_STOCK_RELEVANT_LINE_ITEM_TYPES, true)) {
                continue;
            }

            $stockMovements[] = StockMovement::create([
                'productId' => $orderLineItem->getProductId(),
                'quantity' => $orderLineItem->getQuantity(),
                'source' => StockLocationReference::initialization(),
                'destination' => StockLocationReference::order($order->getId()),
            ]);
        }
        $this->stockMovementService->moveStock($stockMovements, $context);
    }

    private function lockProductStocks(string $orderId, Context $context): void
    {
        $this->entityManager->lockPessimistically(
            StockDefinition::class,
            [
                'product.orderLineItems.order.id' => $orderId,
                'product.orderLineItems.type' => self::ORDER_STOCK_RELEVANT_LINE_ITEM_TYPES,
            ],
            $context,
        );
    }

    private function getOrder(string $orderId, Context $context, array $associations): OrderEntity
    {
        /** @var OrderEntity $order */
        $order = $this->entityManager->findByPrimaryKey(OrderDefinition::class, $orderId, $context, $associations);
        if ($order === null) {
            throw OrderStockInitializerException::orderDoesNotExist($orderId);
        }

        return $order;
    }
}
