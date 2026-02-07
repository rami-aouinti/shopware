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
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Events\MaximumQuantitiesForNewReturnOrderLineItemsCalculatedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ReturnOrderLineItemService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function getMaximumQuantitiesForNewReturnOrderLineItems(array $orderIds, Context $context): array
    {
        // The initial fetch of the order to calculate the maximum returnable quantity is done separately from any fetch
        // of the same order in a subscriber. That means a subscriber may fetch the same orders again but with different
        // line items. To counter this, we run this code in a transaction so that all fetches fetch the same order.
        // See this issue: https://github.com/pickware/shopware-plugins/issues/8612
        $event = $this->entityManager->runInTransactionWithRetry(
            function() use (
                $orderIds,
                $context
            ): MaximumQuantitiesForNewReturnOrderLineItemsCalculatedEvent {
                /** @var OrderCollection $orders */
                $orders = $this->entityManager->findBy(
                    OrderDefinition::class,
                    ['id' => $orderIds],
                    $context,
                    [
                        'lineItems',
                        'pickwareErpReturnOrders.lineItems',
                        'pickwareErpReturnOrders.state',
                    ],
                );
                $result = $this->calculateMaximumQuantitiesForNewReturnOrderLineItems($orders);

                return $this->eventDispatcher->dispatch(
                    new MaximumQuantitiesForNewReturnOrderLineItemsCalculatedEvent($result, $context),
                );
            },
        );

        return $event->getMaximumQuantities();
    }

    /**
     * Re-assigns all provided return reasons of $countedReturnedProducts to the return order line items of the given
     * return order IDs.
     *
     * Existing line items are deleted, recreated and the return reasons are re-assigned with priority to the oldest
     * return order line items. The return reasons are distributed by the following priority:
     * 1. Same return reasons
     * 2. Line items with higher quantity than the return reason quantity
     * 3. Line items with lower quantity than the return reason quantity
     * The line items with the same priority are always sorted by the distance to the return reason quantity.
     *
     * @deprecated Removed with Pickware ERP 5.0.0 without replacement.
     */
    public function assignReturnReasonsToReturnOrderLineItems(
        array $returnOrderIds,
        array $countedReturnedProducts,
        Context $context,
    ): void {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($returnOrderIds, $countedReturnedProducts, $context): void {
                $this->entityManager->lockPessimistically(
                    ReturnOrderLineItemDefinition::class,
                    ['returnOrderId' => $returnOrderIds],
                    $context,
                );
                $returnOrderLineItems = $this->entityManager->findBy(
                    ReturnOrderLineItemDefinition::class,
                    ['returnOrderId' => $returnOrderIds],
                    $context,
                );
                $returnOrderLineItems->sort(fn(ReturnOrderLineItemEntity $lhs, ReturnOrderLineItemEntity $rhs) => (
                    $lhs->getCreatedAt() <=> $rhs->getCreatedAt()
                ));

                $returnReasonsByProductId = [];
                foreach ($countedReturnedProducts as $returnedProduct) {
                    $returnReasonsByProductId[$returnedProduct['productId']] ??= [];
                    $returnReasonsByProductId[$returnedProduct['productId']][] = [
                        'quantity' => $returnedProduct['quantity'],
                        'reason' => $returnedProduct['reason'],
                    ];
                }

                $lineItemPayloads = [];
                /** @var ReturnOrderLineItemEntity $returnOrderLineItem */
                foreach ($returnOrderLineItems as $returnOrderLineItem) {
                    $lineItemPayloadCopy = self::makePayloadCopyOfReturnOrderLineItem($returnOrderLineItem);
                    $returnReasons = $returnReasonsByProductId[$returnOrderLineItem->getProductId()] ?? [];
                    self::sortReturnReasons($returnReasons, $returnOrderLineItem);

                    $quantityToDistribute = $returnOrderLineItem->getQuantity();
                    while ($quantityToDistribute > 0) {
                        $returnReasonToDistribute = array_shift($returnReasons);
                        if ($returnReasonToDistribute === null) {
                            // No more return reasons to distribute, we leave the line item as is.
                            $lineItemPayloads[] = [
                                ...$lineItemPayloadCopy,
                                'quantity' => $quantityToDistribute,
                            ];
                            unset($returnReasonsByProductId[$returnOrderLineItem->getProductId()]);
                            break;
                        }
                        $quantity = min($quantityToDistribute, $returnReasonToDistribute['quantity']);
                        $quantityToDistribute -= $quantity;
                        $returnReasonToDistribute['quantity'] -= $quantity;
                        $lineItemPayloads[] = [
                            ...$lineItemPayloadCopy,
                            'reason' => $returnReasonToDistribute['reason'],
                            'quantity' => $quantity,
                        ];
                        if ($returnReasonToDistribute['quantity'] > 0) {
                            array_unshift($returnReasons, $returnReasonToDistribute);
                        }
                    }
                    $returnReasonsByProductId[$returnOrderLineItem->getProductId()] = $returnReasons;
                }

                // When splitting line items it might be possible to create two (identical) line items. These line items
                // are deduplicated.
                $deduplicatedLineItemPayloads = [];
                foreach ($lineItemPayloads as $lineItemPayload) {
                    $searchPayload = $lineItemPayload;
                    unset($searchPayload['quantity']);
                    $searchPayloads = $deduplicatedLineItemPayloads;
                    foreach ($searchPayloads as &$payload) {
                        unset($payload['quantity']);
                    }
                    unset($payload);

                    $deduplicatedIndex = array_search($searchPayload, $searchPayloads, false);
                    if ($deduplicatedIndex !== false) {
                        $deduplicatedLineItemPayloads[$deduplicatedIndex]['quantity'] += $lineItemPayload['quantity'];
                    } else {
                        $deduplicatedLineItemPayloads[] = $lineItemPayload;
                    }
                }

                $this->entityManager->delete(
                    ReturnOrderLineItemDefinition::class,
                    $returnOrderLineItems->getIds(),
                    $context,
                );
                $this->entityManager->create(
                    ReturnOrderLineItemDefinition::class,
                    $deduplicatedLineItemPayloads,
                    $context,
                );
            },
        );
    }

    public function assignPositionsToReturnOrderLineItemPayloads(array &$lineItemPayloads): void
    {
        usort($lineItemPayloads, function(array $lhs, array $rhs): int {
            if (isset($lhs['productNumber'])) {
                if (isset($rhs['productNumber'])) {
                    return $lhs['productNumber'] <=> $rhs['productNumber'];
                }

                return -1;
            }

            if (isset($rhs['productNumber'])) {
                return 1;
            }

            return 0;
        });

        $lineItemCollection = ImmutableCollection::create($lineItemPayloads);
        $position = 1;
        foreach ($lineItemPayloads as &$lineItemPayload) {
            if (isset($lineItemPayload['position'])) {
                continue;
            }

            while ($lineItemCollection->containsElementSatisfying(fn(array $lineItem) => ($lineItem['position'] ?? null) === $position)) {
                $position++;
            }

            $lineItemPayload['position'] = $position;
            $position++;
        }
        unset($lineItemPayload);
    }

    public function insertReturnOrderLineItemPayloadAtDeterminedPosition(array &$lineItemPayloads, array $newLineItemPayload): void
    {
        if (!isset($newLineItemPayload['position'])) {
            $newLineItemPayload['position'] = max(0, 0, ...array_column($lineItemPayloads, 'position')) + 1;
        }

        foreach ($lineItemPayloads as &$lineItemPayload) {
            if (isset($lineItemPayload['position']) && $lineItemPayload['position'] >= $newLineItemPayload['position']) {
                $lineItemPayload['position'] += 1;
            }
        }
        unset($lineItemPayload);

        $lineItemPayloads[] = $newLineItemPayload;
    }

    /**
     * Does not copy the ID, of the return order line item
     */
    private static function makePayloadCopyOfReturnOrderLineItem(ReturnOrderLineItemEntity $returnOrderLineItem): array
    {
        return [
            'type' => $returnOrderLineItem->getType(),
            'name' => $returnOrderLineItem->getName(),
            'quantity' => $returnOrderLineItem->getQuantity(),
            'priceDefinition' => $returnOrderLineItem->getPriceDefinition(),
            'price' => $returnOrderLineItem->getPrice(),
            'unitPrice' => $returnOrderLineItem->getUnitPrice(),
            'totalPrice' => $returnOrderLineItem->getTotalPrice(),
            'reason' => $returnOrderLineItem->getReason(),
            'productId' => $returnOrderLineItem->getProductId(),
            'productNumber' => $returnOrderLineItem->getProductNumber(),
            'returnOrderId' => $returnOrderLineItem->getReturnOrderId(),
            'orderLineItemId' => $returnOrderLineItem->getOrderLineItemId(),
            'position' => $returnOrderLineItem->getPosition(),
        ];
    }

    private static function sortReturnReasons(array &$returnReasons, ReturnOrderLineItemEntity $lineItem): void
    {
        usort($returnReasons, function(array $lhs, array $rhs) use ($lineItem): int {
            $existingReturnReason = $lineItem->getReason();
            $quantityToReturn = $lineItem->getQuantity();
            $lhsReason = $lhs['reason'];
            $rhsReason = $rhs['reason'];

            // Priority 1: Same return reasons
            if ($lhsReason !== $rhsReason && $lhsReason === $existingReturnReason) {
                return -1;
            }
            if ($lhsReason !== $rhsReason && $rhsReason === $existingReturnReason) {
                return 1;
            }

            // Priority 2: Line items with higher quantity than the return reason quantity
            if ($lhs['quantity'] >= $quantityToReturn && $rhs['quantity'] < $quantityToReturn) {
                return -1;
            }
            if ($rhs['quantity'] >= $quantityToReturn && $lhs['quantity'] < $quantityToReturn) {
                return 1;
            }

            // Sort all three groups by the distance to the line item quantity (closest first) This ensures, that a
            // matching line item will be selected first.
            $lhsDistance = abs($lhs['quantity'] - $quantityToReturn);
            $rhsDistance = abs($rhs['quantity'] - $quantityToReturn);

            return $lhsDistance <=> $rhsDistance;
        });
    }

    /**
     * For the given orders, returns the maximum quantities that should be used in new return order line items that
     * will reference an order line item.
     *
     * Please note that this method cannot be used to validate the quantity of an existing return order line item
     * because it does not take this return order line item into account.
     *
     * @return array (map) for each order: maximum quantity for a return order line item referencing an order line item:
     * [
     *   'order-id-1' => [
     *     'order-line-item-id-1' => 5,
     *     'order-line-item-id-2' => 0,
     *   ],
     * ]
     *
     * Considers _all_ order line items regardless of type/price/references.
     */
    private function calculateMaximumQuantitiesForNewReturnOrderLineItems(OrderCollection $orders): array
    {
        $result = [];
        foreach ($orders as $order) {
            /** @var ReturnOrderCollection $returnOrders */
            $returnOrders = $order->getExtension('pickwareErpReturnOrders')->filter(
                fn(ReturnOrderEntity $returnOrder) => !in_array(
                    $returnOrder->getState()->getTechnicalName(),
                    [
                        // Consider all return orders that are not declined or cancelled.
                        ReturnOrderStateMachine::STATE_DECLINED,
                        ReturnOrderStateMachine::STATE_CANCELLED,
                    ],
                ),
            );

            $result[$order->getId()] = [];

            // Filter shipping cost discounts: Shipping cost discounts are manifested in a separate order delivery with
            // negative shipping costs, but its representation is still in a separate order line item with a price of 0.
            $orderLineItems = $order->getLineItems()->filter(fn(OrderLineItemEntity $orderLineItem) => !(
                $orderLineItem->getType() === LineItem::PROMOTION_LINE_ITEM_TYPE
                && ($orderLineItem->getPayload()['discountScope'] ?? null) === PromotionDiscountEntity::SCOPE_DELIVERY));

            /** @var OrderLineItemEntity $orderLineItems */
            foreach ($orderLineItems as $orderLineItem) {
                $alreadyReturnedQuantity = array_reduce(
                    $returnOrders->getElements(),
                    fn(int $returnedQuantity, ReturnOrderEntity $returnOrder) => $returnedQuantity + array_reduce(
                        $returnOrder->getLineItems()->filter(
                            fn(ReturnOrderLineItemEntity $lineItem) => $lineItem->getOrderLineItemId() === $orderLineItem->getId(),
                        )->getElements(),
                        fn(int $returnedQuantity, ReturnOrderLineItemEntity $lineItem) => $returnedQuantity + $lineItem->getQuantity(),
                        0,
                    ),
                    0,
                );

                $result[$order->getId()][$orderLineItem->getId()] = max(
                    0,
                    $orderLineItem->getQuantity() - $alreadyReturnedQuantity,
                );
            }
        }

        return $result;
    }
}
