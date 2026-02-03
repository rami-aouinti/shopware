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
use Pickware\DalBundle\IdResolver\EntityIdResolver;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Events\ReturnOrdersRequestedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ReturnOrderCreationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntityIdResolver $entityIdResolver,
        private readonly ReturnOrderLineItemService $returnOrderLineItemService,
        private readonly ReturnOrderPayloadService $returnOrderPayloadService,
        private readonly ReturnOrderRefundService $returnOrderRefundService,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createReturnOrders(array $returnOrderPayloads, Context $context): void
    {
        $orderIds = array_unique(array_column($returnOrderPayloads, 'orderId'));
        $maximumQuantities = $this->returnOrderLineItemService->getMaximumQuantitiesForNewReturnOrderLineItems(
            $orderIds,
            $context,
        );

        foreach ($returnOrderPayloads as $returnOrderPayload) {
            foreach ($returnOrderPayload['lineItems'] ?? [] as $lineItem) {
                $orderLineItemId = $lineItem['orderLineItemId'] ?? null;
                if ($orderLineItemId === null) {
                    continue;
                }

                if ($lineItem['quantity'] > $maximumQuantities[$returnOrderPayload['orderId']][$orderLineItemId]) {
                    throw ReturnOrderException::lineItemExceedsMaximumQuantity($orderLineItemId);
                }
            }
        }

        $this->createReturnOrdersWithoutQuantityValidation($returnOrderPayloads, $context);
    }

    private function createReturnOrdersWithoutQuantityValidation(array $returnOrderPayloads, Context $context): void
    {
        $orderIds = array_unique(array_column($returnOrderPayloads, 'orderId'));
        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            ['id' => $orderIds],
            $context,
        );
        if (count($orderIds) > $orders->count()) {
            throw ReturnOrderException::orderNotFound($orderIds, $orders->getKeys());
        }
        $initialReturnOrderStateMachineStateId = $this->initialStateIdLoader
            ->get(ReturnOrderStateMachine::TECHNICAL_NAME);

        foreach ($returnOrderPayloads as &$returnOrderPayload) {
            $returnOrderPayload['id'] ??= Uuid::randomHex();
            $returnOrderPayload['userId'] = ContextExtension::findUserId($context);
            $returnOrderPayload['number'] = $this->numberRangeValueGenerator
                ->getValue(ReturnOrderNumberRange::TECHNICAL_NAME, $context, salesChannelId: null);

            // Prices are recalculated after entity creation
            /** @var OrderEntity $order */
            $order = $orders->get($returnOrderPayload['orderId']);
            if (!isset($returnOrderPayload['price'])) {
                $returnOrderPayload['price'] = ReturnOrderPriceCalculationService::createEmptyCartPrice(
                    $order->getPrice()->getTaxStatus(),
                );
            }

            $returnOrderPayload['stateId'] ??= $initialReturnOrderStateMachineStateId;
            $returnOrderPayload['lineItems'] ??= [];

            $this->returnOrderPayloadService->addDefaultValuesToLineItems(
                $returnOrderPayload['orderId'],
                $returnOrderPayload['lineItems'],
                context: $context,
            );
            $this->returnOrderLineItemService->assignPositionsToReturnOrderLineItemPayloads($returnOrderPayload['lineItems']);
        }
        unset($returnOrderPayload);

        $this->entityManager->runInTransactionWithRetry(
            function() use ($context, $returnOrderPayloads): void {
                $returnOrderIds = array_column($returnOrderPayloads, 'id');
                $this->entityManager->create(ReturnOrderDefinition::class, $returnOrderPayloads, $context);
                $this->returnOrderRefundService->createRefundForReturnOrders($returnOrderIds, $context);
                $this->eventDispatcher->dispatch(new ReturnOrdersRequestedEvent($returnOrderIds, $context));
            },
        );
    }

    /**
     * The $returnedProducts are assigned to the first return order where the corresponding order can still return the
     * given product. If a return order is empty after assignment, it still is created.
     * Note: If more products are provided than can be returned, the maximum returnable quantity of each order is used
     * instead to create the return orders.
     *
     * @param string[] $orderIds
     * @return string[] The IDs of the created return orders
     */
    public function createReturnOrdersForReturnedProductsInOrders(
        array $orderIds,
        ProductQuantityImmutableCollection $returnedProducts,
        Context $context,
    ): array {
        $returnableLineItemQuantities = $this->returnOrderLineItemService
            ->getMaximumQuantitiesForNewReturnOrderLineItems($orderIds, $context);

        $orderLineItemIds = array_keys(array_merge(...array_values($returnableLineItemQuantities)));
        /** @var OrderLineItemCollection $orderLineItems */
        $orderLineItems = $this->entityManager->findBy(
            OrderLineItemDefinition::class,
            ['id' => $orderLineItemIds],
            $context,
        );
        $receivedStateId = $this->entityIdResolver->resolveIdForStateMachineState(
            stateMachineTechnicalName: ReturnOrderStateMachine::TECHNICAL_NAME,
            stateTechnicalName: ReturnOrderStateMachine::STATE_RECEIVED,
        );

        $returnOrderPayloads = [];
        $productsLeftToReturn = $returnedProducts->groupByProductId()->asArray();
        foreach ($returnableLineItemQuantities as $orderId => $returnableLineItemQuantity) {
            $returnOrderLineItems = [];
            foreach ($returnableLineItemQuantity as $orderLineItemId => $maximumReturnableQuantity) {
                $orderLineItem = $orderLineItems->get($orderLineItemId);
                if ($orderLineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                    foreach ($productsLeftToReturn as &$productLeftToReturn) {
                        if (
                            $productLeftToReturn->getQuantity() <= 0
                            || $productLeftToReturn->getProductId() !== $orderLineItem->getProductId()
                        ) {
                            continue;
                        }
                        $quantity = min($productLeftToReturn->getQuantity(), $maximumReturnableQuantity);
                        $returnOrderLineItems[] = [
                            'orderLineItemId' => $orderLineItemId,
                            'quantity' => $quantity,
                        ];

                        $productLeftToReturn = new ProductQuantity(
                            $productLeftToReturn->getProductId(),
                            $productLeftToReturn->getQuantity() - $quantity,
                        );

                        break;
                    }
                    unset($productLeftToReturn);
                } elseif ($orderLineItem->getPrice()->getUnitPrice() <= 0) {
                    $returnOrderLineItems[] = [
                        'orderLineItemId' => $orderLineItemId,
                        'quantity' => $maximumReturnableQuantity,
                    ];
                }
            }

            $returnOrderPayloads[] = [
                'id' => Uuid::randomHex(),
                'orderId' => $orderId,
                'stateId' => $receivedStateId,
                'lineItems' => $returnOrderLineItems,
            ];
        }

        if (!empty($returnOrderPayloads)) {
            $this->createReturnOrdersWithoutQuantityValidation($returnOrderPayloads, $context);
        }

        return array_column($returnOrderPayloads, 'id');
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
        foreach ($returnOrderPayloads as &$returnOrderPayload) {
            $returnOrderPayload['lineItems'] ??= [];

            $lineItemsWithProductIdButWithoutLineItemId = array_filter(
                $returnOrderPayload['lineItems'],
                fn($lineItem) => array_key_exists('productId', $lineItem) && !array_key_exists('orderLineItemId', $lineItem),
            );
            if (count($lineItemsWithProductIdButWithoutLineItemId) > 0) {
                $orderLineItems = $this->entityManager->findBy(
                    OrderLineItemDefinition::class,
                    ['orderId' => $returnOrderPayload['orderId']],
                    $context,
                );
                foreach ($returnOrderPayload['lineItems'] as &$lineItem) {
                    if (isset($lineItem['productId'])) {
                        $orderLineItem = $orderLineItems->filter(
                            fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getProductId() === $lineItem['productId'],
                        )->first();
                        $lineItem['orderLineItemId'] ??= $orderLineItem?->getId();
                        $lineItem['quantity'] ??= $orderLineItem?->getQuantity();
                    }
                }
                unset($lineItem);
            }

            foreach ($returnOrderPayload['lineItems'] as &$lineItem) {
                if (isset($lineItem['productId'])) {
                    $lineItem['type'] ??= LineItem::PRODUCT_LINE_ITEM_TYPE;
                }
            }
            unset($lineItem);
        }
        unset($returnOrderPayload);

        $this->createReturnOrdersWithoutQuantityValidation($returnOrderPayloads, $context);
    }
}
