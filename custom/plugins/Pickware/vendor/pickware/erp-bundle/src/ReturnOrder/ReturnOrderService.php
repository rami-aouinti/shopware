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
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\ReturnOrder\Events\CompletelyReturnedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Events\PartiallyReturnedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Events\ReturnOrderCancelledEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Events\ReturnOrderDeclinedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Events\ReturnOrderNonPhysicalLineItemsAddedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Events\ReturnOrdersApprovedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Events\ReturnOrdersCompletedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Events\StockMovedFromReturnOrdersEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use RuntimeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ReturnOrderService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockMovementService $stockMovementService,
        private readonly ReturnOrderCreationService $returnOrderCreationService,
        private readonly ReturnOrderLineItemService $returnOrderLineItemService,
        private readonly ReturnOrderPayloadService $returnOrderPayloadService,
        private readonly StateTransitionService $stateTransitionService,
        private readonly StockingStrategy $stockingStrategy,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return array (map) for each order: quantity that can be returned by product id (not order line item id)
     */
    private function getProductStocksInOrders(array $orderIds, Context $context): array
    {
        $stocks = $this->entityManager->findBy(
            StockDefinition::class,
            ['orderId' => $orderIds],
            $context,
        )->getElements();

        $stocksByOrder = array_fill_keys($orderIds, []);
        /** @var StockEntity $stock */
        foreach ($stocks as $stock) {
            $stocksByOrder[$stock->getOrderId()][$stock->getProductId()] = $stock->getQuantity();
        }

        return $stocksByOrder;
    }

    /**
     * @deprecated Use {@link createReturnOrders} instead.
     *
     * This method allows to only pass a product ID in the line items of the return order payload. The corresponding
     * order line item ID is then automatically determined by the product ID. Since this behavior is not always
     * deterministic, this method was deprecated. Use {@link createReturnOrders} instead where the order line item is
     * not determined automatically. This method will be removed with v5.0.0.
     */
    public function requestReturnOrders(array $returnOrderPayloads, Context $context): void
    {
        $this->returnOrderCreationService->requestReturnOrders($returnOrderPayloads, $context);
    }

    /**
     * Updates the line items of the return order to match the $returnedProducts.
     *
     * Products that are contained in the return order but not in the $returnedProducts are removed. Product that are
     * contained in $returnedProducts are added as line items with price 0.00. LineItems for products that already
     * exists in the return order and in the $returnedProducts are updated.
     *
     * Be aware that it is necessary to recalculate the prices after this method was called.
     *
     * @param ImmutableCollection<ReturnedProduct> $returnedProducts
     */
    public function setProductsOfReturnOrder(
        string $returnOrderId,
        ImmutableCollection $returnedProducts,
        Context $context,
    ): void {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            throw new RuntimeException(
                'This method is not available when the feature "goods-receipt-for-return-order" is active.',
            );
        }

        $this->entityManager->runInTransactionWithRetry(
            function() use ($returnOrderId, $returnedProducts, $context): void {
                /** @var ReturnOrderEntity $returnOrder */
                $returnOrder = $this->entityManager->getByPrimaryKey(
                    ReturnOrderDefinition::class,
                    $returnOrderId,
                    $context,
                    ['lineItems'],
                );

                $returnedProductIds = $returnedProducts->map(fn($productQuantity) => $productQuantity->getProductId());

                // In the context of receiving a return order only line items of type product can be missing.
                $missingLineItemIds = $returnOrder
                    ->getLineItems()
                    ->filter(
                        fn(ReturnOrderLineItemEntity $lineItem) =>
                            !$returnedProductIds->containsElementEqualTo($lineItem->getProductId())
                            && $lineItem->getType() === ReturnOrderLineItemDefinition::TYPE_PRODUCT,
                    )
                    ->getIds();

                $this->entityManager->delete(
                    ReturnOrderLineItemDefinition::class,
                    array_values($missingLineItemIds),
                    $context,
                );

                /** @var ReturnedProduct[] $lineItemInsertPayload */
                $lineItemInsertPayload = [];
                $lineItemUpdatePayload = [];
                foreach ($returnedProducts as $returnedProduct) {
                    $lineItemPayload = [
                        'quantity' => $returnedProduct->getQuantity(),
                        'reason' => $returnedProduct->getReturnReason(),
                    ];

                    $matchingLineItem = $returnOrder
                        ->getLineItems()
                        ->filter(fn($lineItem) => $lineItem->getProductId() === $returnedProduct->getProductId())
                        ->first();

                    // Update the matching line item with the values from the returned product
                    if ($matchingLineItem !== null) {
                        $lineItemPayload = [
                            'id' => $matchingLineItem->getId(),
                            'priceDefinition' => $matchingLineItem->getPriceDefinition()->jsonSerialize(),
                            ...$lineItemPayload,
                        ];
                        $lineItemPayload['priceDefinition']['quantity'] = $returnedProduct->getQuantity();
                        $lineItemUpdatePayload[] = $lineItemPayload;
                    } else {
                        $lineItemInsertPayload[] = [
                            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                            'productId' => $returnedProduct->getProductId(),
                            'returnOrderId' => $returnOrder->getId(),
                            ...$lineItemPayload,
                        ];
                    }
                }

                $this->returnOrderPayloadService->addDefaultValuesToLineItems(
                    $returnOrder->getOrderId(),
                    $lineItemInsertPayload,
                    $context,
                );

                $this->entityManager->upsert(
                    ReturnOrderLineItemDefinition::class,
                    [
                        ...$lineItemInsertPayload,
                        ...$lineItemUpdatePayload,
                    ],
                    $context,
                );
            },
        );
    }

    /**
     * Marks a return order as received.
     *
     * The difference to `receiveProductsForReturnOrder` is that this method marks the return order as received as is.
     * No line items are changed.
     */
    public function markReturnOrderAsReceived(string $returnOrderId, Context $context): void
    {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            throw new RuntimeException(
                'This method is not available when the feature "goods-receipt-for-return-order" is active.',
            );
        }

        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            [
                'lineItems',
                'order.lineItems',
                'order.orderCustomer',
            ],
        );

        $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
            new Transition(
                ReturnOrderDefinition::ENTITY_NAME,
                $returnOrderId,
                ReturnOrderStateMachine::TRANSITION_RECEIVE,
                'stateId',
            ),
            $context,
        );

        if ($this->isReturnOrderCreatedInFull($returnOrder, $context)) {
            $this->eventDispatcher->dispatch(CompletelyReturnedEvent::createFromOrder($context, $returnOrder->getOrder()));
        } else {
            $this->eventDispatcher->dispatch(PartiallyReturnedEvent::createFromOrder($context, $returnOrder->getOrder()));
        }
    }

    /**
     * @param string[] $returnOrderIds
     */
    public function approveReturnOrders(array $returnOrderIds, Context $context): void
    {
        foreach ($returnOrderIds as $returnOrderId) {
            $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
                new Transition(
                    ReturnOrderDefinition::ENTITY_NAME,
                    $returnOrderId,
                    ReturnOrderStateMachine::TRANSITION_APPROVE,
                    'stateId',
                ),
                $context,
            );
        }
        $this->eventDispatcher->dispatch(new ReturnOrdersApprovedEvent($returnOrderIds, $context));
    }

    public function cancelReturnOrder(string $returnOrderId, Context $context): void
    {
        $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
            new Transition(
                ReturnOrderDefinition::ENTITY_NAME,
                $returnOrderId,
                ReturnOrderStateMachine::TRANSITION_CANCEL,
                'stateId',
            ),
            $context,
        );

        $this->eventDispatcher->dispatch(new ReturnOrderCancelledEvent($returnOrderId, $context));
    }

    public function declineReturnOrder(string $returnOrderId, Context $context): void
    {
        $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
            new Transition(
                ReturnOrderDefinition::ENTITY_NAME,
                $returnOrderId,
                ReturnOrderStateMachine::TRANSITION_DECLINE,
                'stateId',
            ),
            $context,
        );

        $this->eventDispatcher->dispatch(new ReturnOrderDeclinedEvent($returnOrderId, $context));
    }

    /**
     * Creates a new return order line item for each of its associated order lines item which is not of type product
     * and has a negative total price.
     */
    public function addNonPhysicalLineItemsFromOrder(
        string $returnOrderId,
        Context $context,
    ): void {
        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            [
                'order.lineItems',
            ],
        );

        $maximumQuantities = $this->returnOrderLineItemService
            ->getMaximumQuantitiesForNewReturnOrderLineItems([$returnOrder->getOrderId()], $context)[$returnOrder->getOrderId()];
        $discountLineItems = ImmutableCollection::create($returnOrder->getOrder()->getLineItems())
            ->filter(
                fn(OrderLineItemEntity $lineItem) =>
                    $lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE && $lineItem->getTotalPrice() < 0,
            )
            ->filter(fn(OrderLineItemEntity $lineItem) => isset($maximumQuantities[$lineItem->getId()]) && $maximumQuantities[$lineItem->getId()] > 0)
            ->map(function(OrderLineItemEntity $lineItem) use ($returnOrderId, $maximumQuantities): array {
                $priceDefinition = $lineItem->getPriceDefinition();
                if ($priceDefinition !== null && $priceDefinition->getType() === PercentagePriceDefinition::TYPE) {
                    // We do not intend to recalculate PercentagePriceDefinitions. Hence, we transform them into
                    // AbsolutePriceDefinition which we _can_ recalculate. The quantity stays the same. Therefore, the
                    // current `price` is still correct and does not need to be recalculated.
                    $priceDefinition = new AbsolutePriceDefinition($lineItem->getPrice()->getTotalPrice());
                }

                return [
                    'returnOrderId' => $returnOrderId,
                    'orderLineItemId' => $lineItem->getId(),
                    'reason' => ReturnOrderLineItemDefinition::REASON_UNKNOWN,
                    'type' => $lineItem->getType(),
                    'name' => $lineItem->getLabel(),
                    'productId' => null,
                    'productNumber' => $lineItem->getPayload()['productNumber'] ?? null,
                    'quantity' => $maximumQuantities[$lineItem->getId()],
                    'price' => $lineItem->getPrice(),
                    'priceDefinition' => $priceDefinition,
                ];
            })
            ->asArray();

        $this->entityManager->create(
            ReturnOrderLineItemDefinition::class,
            $discountLineItems,
            $context,
        );

        $this->eventDispatcher->dispatch(new ReturnOrderNonPhysicalLineItemsAddedEvent($returnOrderId, $context));
    }

    /**
     * Moves the given stock quantities into the return order. The corresponding order is used as stock source for as
     * much stock as possible. If the stock in the order does not suffice, stock from the unknown stock location is
     * moved into the return order until the given stock quantity is achieved.
     *
     * No restock/dispose values are set or used here.
     *
     * Note: since the quantity is mapped by product id ($productId => $returnQuantity), we do not need a special
     * handling for multiple line items of the same product.
     *
     * @param array<string, array<string, int>> $productQuantitiesByReturnOrderId
     */
    public function moveStockIntoReturnOrders(
        array $productQuantitiesByReturnOrderId,
        Context $context,
    ): void {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            throw new RuntimeException(
                'This method is not available when the feature "goods-receipt-for-return-order" is active.',
            );
        }

        $returnOrderIds = array_keys($productQuantitiesByReturnOrderId);
        /** @var ReturnOrderCollection $returnOrders */
        $returnOrders = $this->entityManager->findBy(ReturnOrderDefinition::class, ['id' => $returnOrderIds], $context);
        if (count($returnOrderIds) > $returnOrders->count()) {
            throw ReturnOrderException::returnOrderNotFound($returnOrderIds, $returnOrders->getKeys());
        }

        $orderIds = array_values($returnOrders->map(fn(ReturnOrderEntity $returnOrder) => $returnOrder->getOrderId()));
        $returnableStockByOrderId = $this->getProductStocksInOrders($orderIds, $context);

        $stockMovements = [];
        foreach ($returnOrders as $returnOrder) {
            $orderId = $returnOrder->getOrderId();
            foreach ($productQuantitiesByReturnOrderId[$returnOrder->getId()] as $productId => $returnQuantity) {
                $stockInOrder = $returnableStockByOrderId[$orderId][$productId] ?? 0;
                $returnQuantity = $productQuantitiesByReturnOrderId[$returnOrder->getId()][$productId];

                if ($stockInOrder < $returnQuantity) {
                    // If there is not enough stock for the returned quantity in the order, we move all existing
                    // stock from the order to the return order and the remaining difference from unknown to
                    // return order directly.
                    $returnQuantityFromOrder = $stockInOrder;
                    $returnQuantityFromUnknown = $returnQuantity - $stockInOrder;
                } else {
                    // Move all returned quantity from the order to the return order otherwise.
                    $returnQuantityFromOrder = $returnQuantity;
                    $returnQuantityFromUnknown = 0;
                }
                $returnableStockByOrderId[$orderId][$productId] = $stockInOrder - $returnQuantityFromOrder;

                if ($returnQuantityFromUnknown > 0) {
                    $stockMovements[] = StockMovement::create([
                        'productId' => $productId,
                        'quantity' => $returnQuantityFromUnknown,
                        'source' => StockLocationReference::unknown(),
                        'destination' => StockLocationReference::returnOrder($returnOrder->getId()),
                        'userId' => ContextExtension::findUserId($context),
                    ]);
                }
                if ($returnQuantityFromOrder > 0) {
                    $stockMovements[] = StockMovement::create([
                        'productId' => $productId,
                        'quantity' => $returnQuantityFromOrder,
                        'source' => StockLocationReference::order($orderId),
                        'destination' => StockLocationReference::returnOrder($returnOrder->getId()),
                        'userId' => ContextExtension::findUserId($context),
                    ]);
                }
            }
        }

        if (count($stockMovements) > 0) {
            $this->stockMovementService->moveStock($stockMovements, $context);
        }
    }

    /**
     * @param String[] $returnOrderIds
     */
    public function completeReturnOrders(array $returnOrderIds, Context $context): void
    {
        foreach ($returnOrderIds as $returnOrderId) {
            $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
                new Transition(
                    ReturnOrderDefinition::ENTITY_NAME,
                    $returnOrderId,
                    ReturnOrderStateMachine::TRANSITION_COMPLETE,
                    'stateId',
                ),
                $context,
            );
        }
        $this->eventDispatcher->dispatch(new ReturnOrdersCompletedEvent($returnOrderIds, $context));
    }

    public function moveStockFromReturnOrders(
        array $stockAdjustmentsByReturnOrderId,
        array $warehouseIdsByReturnOrderId,
        Context $context,
    ): void {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            throw new RuntimeException(
                'This method is not available when the feature "goods-receipt-for-return-order" is active.',
            );
        }

        $returnOrderIds = array_keys($stockAdjustmentsByReturnOrderId);
        /** @var ReturnOrderCollection $returnOrders */
        $returnOrders = $this->entityManager->findBy(
            ReturnOrderDefinition::class,
            ['id' => $returnOrderIds],
            $context,
        );
        if (count($returnOrderIds) > $returnOrders->count()) {
            throw ReturnOrderException::returnOrderNotFound($returnOrderIds, $returnOrders->getKeys());
        }

        $stockMovements = [];
        foreach ($returnOrders as $returnOrder) {
            $returnOrderStockMovements = $this->parseStockAdjustments(
                $stockAdjustmentsByReturnOrderId[$returnOrder->getId()],
                $returnOrder->getId(),
                $warehouseIdsByReturnOrderId[$returnOrder->getId()],
                $context,
            );

            $stockMovements = array_merge(
                $stockMovements,
                $returnOrderStockMovements,
            );
        }

        if (count($stockMovements) > 0) {
            $this->stockMovementService->moveStock($stockMovements, $context);
            $this->eventDispatcher->dispatch(new StockMovedFromReturnOrdersEvent(
                ImmutableCollection::create($stockMovements),
                $context,
            ));
        }
    }

    /**
     * Note: Stock adjustments are not mapped by product. Hence, we can handle multiple line items of the same product
     * and for each stock adjustment a separate stock movement is created.
     *
     * @return StockMovement[]
     */
    private function parseStockAdjustments(
        array $stockAdjustments,
        string $returnOrderId,
        string $warehouseId,
        Context $context,
    ): array {
        $stockMovements = [];
        $productQuantitiesRestock = [];
        foreach ($stockAdjustments as $stockAdjustment) {
            if ($stockAdjustment['restock'] > 0) {
                $productQuantitiesRestock[] = new ProductQuantity(
                    $stockAdjustment['productId'],
                    $stockAdjustment['restock'],
                );
            }
            if ($stockAdjustment['dispose'] > 0) {
                $stockMovements[] = StockMovement::create([
                    'productId' => $stockAdjustment['productId'],
                    'quantity' => $stockAdjustment['dispose'],
                    'source' => StockLocationReference::returnOrder($returnOrderId),
                    'destination' => StockLocationReference::unknown(),
                    'userId' => ContextExtension::findUserId($context),
                ]);
            }
        }

        // Add stock movements into warehouse using the stocking strategy
        return array_merge(
            $stockMovements,
            $this->stockingStrategy
                ->calculateStockingSolution(new StockingRequest($productQuantitiesRestock, $warehouseId), $context)
                ->createStockMovementsWithSource(StockLocationReference::returnOrder($returnOrderId)),
        );
    }

    /**
     * @return array<string, array<string, int>> existing (i.e. not-deleted products), positive product quantities by
     * return order id. e.g.:
     *   [
     *     return-order-id-1: [
     *       product-id-1: 5,
     *       product-id-3: 10,
     *     ],
     *   ]
     */
    public function getProductQuantitiesByReturnOrderId(array $returnOrderIds, Context $context): array
    {
        /** @var ReturnOrderCollection $returnOrders */
        $returnOrders = $this->entityManager->findBy(
            ReturnOrderDefinition::class,
            ['id' => $returnOrderIds],
            $context,
            [
                'lineItems',
            ],
        );

        if (count($returnOrderIds) > $returnOrders->count()) {
            throw ReturnOrderException::returnOrderNotFound($returnOrderIds, $returnOrders->getKeys());
        }

        $productQuantitiesByReturnOrderId = [];
        foreach ($returnOrders as $returnOrder) {
            $relevantReturnOrderLineItems = $returnOrder->getLineItems()->filter(
                fn(ReturnOrderLineItemEntity $lineItem) => ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) && ($lineItem->getProductId() !== null) && ($lineItem->getQuantity() > 0),
            )->getElements();
            $productQuantities = [];
            foreach ($relevantReturnOrderLineItems as $lineItem) {
                // Account for multiple line items of the same product
                if (!array_key_exists($lineItem->getProductId(), $productQuantities)) {
                    $productQuantities[$lineItem->getProductId()] = 0;
                }
                $productQuantities[$lineItem->getProductId()] += $lineItem->getQuantity();
            }
            $productQuantitiesByReturnOrderId[$returnOrder->getId()] = $productQuantities;
        }

        return $productQuantitiesByReturnOrderId;
    }

    private function isReturnOrderCreatedInFull(ReturnOrderEntity $returnOrder, Context $context): bool
    {
        /** @var ReturnOrderCollection $allReturnOrdersOfOrder */
        $allReturnOrdersOfOrder = $this->entityManager->findBy(
            ReturnOrderDefinition::class,
            ['orderId' => $returnOrder->getOrderId()],
            $context,
            [
                'lineItems',
            ],
        );

        // Add all return order line items to an array, so we can determine if the order is returned completely or partially
        $returnableOrderLineItems = [];
        foreach ($allReturnOrdersOfOrder as $returnOrderByOrder) {
            foreach ($returnOrderByOrder->getLineItems() as $returnOrderLineItem) {
                $returnableOrderLineItems[] = $returnOrderLineItem;
            }
        }

        $orderLineItems = array_filter(
            $returnOrder->getOrder()->getLineItems()->getElements(),
            fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE,
        );

        /** @var OrderLineItemEntity $orderLineItem */
        foreach ($orderLineItems as $orderLineItem) {
            $returnedQuantity = array_reduce(
                array_filter(
                    $returnableOrderLineItems,
                    fn(ReturnOrderLineItemEntity $returnOrderLineItem) =>
                        $returnOrderLineItem->getProductId() === $orderLineItem->getProductId(),
                ),
                fn(int $result, ReturnOrderLineItemEntity $item) => $result + $item->getQuantity(),
                0,
            );
            // If a returnOrderLineItem is partially returned, the return order is not created in full.
            if ($returnedQuantity < $orderLineItem->getQuantity()) {
                return false;
            }
        }

        return true;
    }
}
