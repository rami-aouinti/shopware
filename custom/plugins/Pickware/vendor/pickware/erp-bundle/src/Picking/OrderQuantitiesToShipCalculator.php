<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Order\Model\PickwareErpPickwareOrderLineItemEntity;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\Stock\InternalReservedStockUpdater;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class OrderQuantitiesToShipCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @deprecated Will be removed with 5.0.0.
     * Use {@link calculateProductsToShipForOrdersIncludingExternalFulfillments()} or
     * {@link calculateProductsToShipForOrdersExcludingExternalFulfillments()} instead.
     */
    public function calculateProductsToShipForOrder(
        string $orderId,
        Context $context,
    ): ProductQuantityImmutableCollection {
        return $this->calculateProductsToShipForOrders([$orderId], $context)[$orderId];
    }

    /**
     * @param string[] $orderIds
     * @return array<string, ProductQuantityImmutableCollection> An array with a ProductQuantityImmutableCollection as
     * value for each passed order ID as key
     * @deprecated Will be removed with 5.0.0.
     * Use {@link calculateProductsToShipForOrdersIncludingExternalFulfillments()} or
     * {@link calculateProductsToShipForOrdersExcludingExternalFulfillments()} instead.
     */
    public function calculateProductsToShipForOrders(array $orderIds, Context $context): array
    {
        return $this->calculateProductsToShipForOrdersInternal(
            $orderIds,
            $context,
            includeExternallyFulfilledQuantities: false,
        );
    }

    /**
     * @param string[] $orderIds
     * @return array<string, ProductQuantityImmutableCollection> An array with a ProductQuantityImmutableCollection as
     * value for each passed order ID as key
     */
    public function calculateProductsToShipForOrdersIncludingExternalFulfillments(
        array $orderIds,
        Context $context,
    ): array {
        return $this->calculateProductsToShipForOrdersInternal(
            $orderIds,
            $context,
            includeExternallyFulfilledQuantities: true,
        );
    }

    /**
     * @param string[] $orderIds
     * @return array<string, ProductQuantityImmutableCollection> An array with a ProductQuantityImmutableCollection as
     * value for each passed order ID as key
     */
    public function calculateProductsToShipForOrdersExcludingExternalFulfillments(
        array $orderIds,
        Context $context,
    ): array {
        return $this->calculateProductsToShipForOrdersInternal(
            $orderIds,
            $context,
            includeExternallyFulfilledQuantities: false,
        );
    }

    /**
     * @param string[] $orderIds
     * @return array<string, ProductQuantityImmutableCollection>
     */
    private function calculateProductsToShipForOrdersInternal(array $orderIds, Context $context, bool $includeExternallyFulfilledQuantities): array
    {
        $orderLineItems = $this->getOrderLineItems($orderIds, $context);
        $quantitiesToPick = $this->getQuantitiesToPickByProduct($orderIds, $orderLineItems, $context, $includeExternallyFulfilledQuantities);

        return array_map(
            function(array $quantitiesToPick) {
                $productQuantities = [];
                foreach ($quantitiesToPick as $productId => $quantityToPick) {
                    if ($quantityToPick <= 0) {
                        continue;
                    }
                    $productQuantities[] = new ProductQuantity($productId, $quantityToPick);
                }

                return new ProductQuantityImmutableCollection($productQuantities);
            },
            $quantitiesToPick,
        );
    }

    /**
     * @deprecated Use {@link calculateLineItemQuantitiesToShipForOrder()} instead. Will be removed in ERP 5.0.0
     */
    public function calculateLineItemsToShipForOrder(
        string $orderId,
        Context $context,
    ): OrderLineItemQuantityCollection {
        $countingMap = $this->calculateLineItemQuantitiesToShipForOrder($orderId, $context);

        // Convert CountingMap to OrderLineItemQuantityCollection for backwards compatibility
        $orderLineItemQuantities = $countingMap->mapToList(
            fn(string $orderLineItemId, int $quantity) => new OrderLineItemQuantity($orderLineItemId, $quantity),
        );

        return new OrderLineItemQuantityCollection($orderLineItemQuantities);
    }

    /**
     * @return CountingMap<string> key: order line item ID, value: quantity to ship
     */
    public function calculateLineItemQuantitiesToShipForOrder(
        string $orderId,
        Context $context,
    ): CountingMap {
        return $this->calculateLineItemsToShipForOrders([$orderId], $context)[$orderId];
    }

    /**
     * @param string[] $orderIds
     * @return array<string, CountingMap<string>> An array with a CountingMap as value for each passed order ID as key
     */
    public function calculateLineItemsToShipForOrders(
        array $orderIds,
        Context $context,
    ): array {
        $quantitiesToPick = $this->getQuantitiesToPickByLineItem(
            orderIds: $orderIds,
            orderLineItems: $this->getOrderLineItems($orderIds, $context),
            context: $context,
            includeExternallyFulfilledQuantities: false,
        );

        return array_map(
            function(array $quantitiesToPick) {
                $quantities = [];
                foreach ($quantitiesToPick as $orderLineItemId => $quantityToPick) {
                    if ($quantityToPick <= 0) {
                        continue;
                    }
                    $quantities[$orderLineItemId] = $quantityToPick;
                }

                return new CountingMap($quantities);
            },
            $quantitiesToPick,
        );
    }

    /**
     * @param string[] $orderIds
     */
    private function getOrderLineItems(array $orderIds, Context $context): OrderLineItemCollection
    {
        $orderLineItemCriteria = new Criteria();
        $orderLineItemCriteria
            ->addFilter(new EqualsAnyFilter('orderId', $orderIds))
            ->addFilter(new EqualsFilter('type', LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('productId', null)]));

        /** @var OrderLineItemCollection */
        return $this->entityManager->findBy(
            OrderLineItemDefinition::class,
            $orderLineItemCriteria,
            $context,
            ['extensions.pickwareErpPickwareOrderLineItem'],
        );
    }

    /**
     * @param string[] $orderIds
     * @return array<string, array<string, int>> first key: order ID, second key: product ID, value: quantity to pick
     */
    private function getQuantitiesToPickByProduct(
        array $orderIds,
        OrderLineItemCollection $orderLineItems,
        Context $context,
        bool $includeExternallyFulfilledQuantities,
    ): array {
        $quantitiesToPickByLineItem = $this->getQuantitiesToPickByLineItem(
            $orderIds,
            $orderLineItems,
            $context,
            $includeExternallyFulfilledQuantities,
        );

        $quantitiesToPickByProduct = [];
        foreach ($quantitiesToPickByLineItem as $orderId => $quantities) {
            $quantitiesToPickByProduct[$orderId] = [];
            foreach ($quantities as $lineItemId => $quantity) {
                $lineItem = $orderLineItems->get($lineItemId);

                $quantitiesToPickByProduct[$orderId][$lineItem->getProductId()] ??= 0;
                $quantitiesToPickByProduct[$orderId][$lineItem->getProductId()] += $quantity;
            }
        }

        return $quantitiesToPickByProduct;
    }

    /**
     * @param string[] $orderIds
     * @return array<string, array<string, int>> first key: order ID, second key: lineItem ID, value: quantity to pick
     */
    private function getQuantitiesToPickByLineItem(
        array $orderIds,
        OrderLineItemCollection $orderLineItems,
        Context $context,
        bool $includeExternallyFulfilledQuantities,
    ): array {
        $returnOrderLineItemCriteria = new Criteria();
        $returnOrderLineItemCriteria
            ->addFilter(new EqualsAnyFilter('returnOrder.orderId', $orderIds))
            ->addFilter(new EqualsFilter('type', ReturnOrderLineItemDefinition::TYPE_PRODUCT))
            ->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('productId', null)]))
            ->addFilter(new EqualsFilter('returnOrder.state.technicalName', InternalReservedStockUpdater::RETURN_ORDER_STATE_ALLOW_STATE));
        /** @var ReturnOrderLineItemCollection $returnOrderLineItems */
        $returnOrderLineItems = $this->entityManager->findBy(
            ReturnOrderLineItemDefinition::class,
            $returnOrderLineItemCriteria,
            $context,
            ['returnOrder'],
        );

        /** @var StockCollection $orderStocks */
        $orderStocks = $this->entityManager->findBy(StockDefinition::class, ['orderId' => $orderIds], $context);

        $quantitiesToPick = array_combine($orderIds, array_fill(0, count($orderIds), []));
        foreach ($orderLineItems as $orderLineItem) {
            $orderId = $orderLineItem->getOrderId();
            $quantitiesToPick[$orderId][$orderLineItem->getId()] ??= 0;
            $quantityToBeFulfilled = $orderLineItem->getQuantity();
            if (!$includeExternallyFulfilledQuantities) {
                /** @var PickwareErpPickwareOrderLineItemEntity|null $pickwareOrderLineItem */
                $pickwareOrderLineItem = $orderLineItem->getExtension('pickwareErpPickwareOrderLineItem');
                $quantityToBeFulfilled -= $pickwareOrderLineItem?->getExternallyFulfilledQuantity() ?? 0;
            }
            $quantitiesToPick[$orderId][$orderLineItem->getId()] += $quantityToBeFulfilled;
        }
        foreach ($returnOrderLineItems as $returnOrderLineItem) {
            $orderId = $returnOrderLineItem->getReturnOrder()->getOrderId();
            $orderLineItemId = $returnOrderLineItem->getOrderLineItemId();

            if ($orderLineItemId === null) {
                continue;
            }

            $quantitiesToPick[$orderId][$orderLineItemId] ??= 0;
            $quantitiesToPick[$orderId][$orderLineItemId] -= $returnOrderLineItem->getQuantity();
        }
        foreach ($orderStocks as $orderStock) {
            $orderId = $orderStock->getOrderId();
            $productId = $orderStock->getProductId();

            $productLineItemsInOrder = $orderLineItems
                ->filter(
                    fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getOrderId() === $orderId && $orderLineItem->getProductId() === $productId,
                );

            foreach ($productLineItemsInOrder as $productLineItemInOrder) {
                if ($orderStock->getQuantity() === 0) {
                    break;
                }

                if (isset($quantitiesToPick[$orderId][$productLineItemInOrder->getId()]) && $quantitiesToPick[$orderId][$productLineItemInOrder->getId()] !== 0) {
                    if ($quantitiesToPick[$orderId][$productLineItemInOrder->getId()] >= $orderStock->getQuantity()) {
                        $quantitiesToPick[$orderId][$productLineItemInOrder->getId()] -= $orderStock->getQuantity();
                        $orderStock->setQuantity(0);
                    } else {
                        $orderStock->setQuantity($orderStock->getQuantity() - $quantitiesToPick[$orderId][$productLineItemInOrder->getId()]);
                        $quantitiesToPick[$orderId][$productLineItemInOrder->getId()] = 0;
                    }
                }
            }
        }

        foreach ($quantitiesToPick as $orderId => $quantities) {
            $quantitiesToPick[$orderId] = array_filter($quantities);
        }

        return $quantitiesToPick;
    }
}
