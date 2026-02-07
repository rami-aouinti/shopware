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
use Pickware\PickwareErpStarter\Order\Model\PickwareErpPickwareOrderLineItemEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class ReservedStockBreakdownService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @return array{
     *     salesChannels: list<array{
     *        salesChannelId: string,
     *        salesChannelName: string,
     *        salesChannelTypeId: string,
     *        quantity: int,
     *     }>,
     *     manuallyCreatedQuantity: int,
     * }
     */
    public function getReservedStockBreakdown(string $productId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('lineItems.productId', $productId),
        );
        $criteria->addFilter(
            new EqualsFilter('lineItems.type', LineItem::PRODUCT_LINE_ITEM_TYPE),
        );
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new MultiFilter(
                        MultiFilter::CONNECTION_OR,
                        [
                            new EqualsFilter('stateMachineState.technicalName', OrderStates::STATE_OPEN),
                            new EqualsFilter('stateMachineState.technicalName', OrderStates::STATE_IN_PROGRESS),
                        ],
                    ),
                ],
            ),
        );
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.pickwareErpPickwareOrderLineItem');
        $criteria->addAssociation('deliveries.stateMachineState');

        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            $criteria,
            $context,
        );

        $salesChannelsMap = [];
        $manuallyCreatedQuantity = 0;

        foreach ($orders as $order) {
            if (!$this->hasRelevantDelivery($order)) {
                continue;
            }

            $salesChannel = $order->getSalesChannel();
            if ($salesChannel === null) {
                continue;
            }

            $lineItems = $order->getLineItems();
            if ($lineItems === null) {
                continue;
            }

            $orderQuantity = $this->calculateOrderQuantity($lineItems, $productId);

            if ($order->getCreatedById() !== null) {
                $manuallyCreatedQuantity += $orderQuantity;
            } else {
                $salesChannelId = $salesChannel->getId();
                if (!isset($salesChannelsMap[$salesChannelId])) {
                    $salesChannelsMap[$salesChannelId] = [
                        'salesChannelId' => $salesChannelId,
                        'salesChannelName' => $salesChannel->getTranslation('name') ?? $salesChannel->getName(),
                        'salesChannelTypeId' => $salesChannel->getTypeId(),
                        'quantity' => 0,
                    ];
                }

                $salesChannelsMap[$salesChannelId]['quantity'] += $orderQuantity;
            }
        }

        return [
            'salesChannels' => array_values($salesChannelsMap),
            'manuallyCreatedQuantity' => $manuallyCreatedQuantity,
        ];
    }

    private function hasRelevantDelivery(OrderEntity $order): bool
    {
        $deliveries = $order->getDeliveries();
        if ($deliveries === null || $deliveries->count() === 0) {
            return true;
        }

        foreach ($deliveries as $delivery) {
            $deliveryState = $delivery->getStateMachineState();
            if ($deliveryState !== null) {
                $deliveryStateTechnicalName = $deliveryState->getTechnicalName();
                if (
                    $deliveryStateTechnicalName !== OrderDeliveryStates::STATE_CANCELLED
                    && $deliveryStateTechnicalName !== OrderDeliveryStates::STATE_SHIPPED
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function calculateOrderQuantity(OrderLineItemCollection $lineItems, string $productId): int
    {
        $orderQuantity = 0;
        foreach ($lineItems as $lineItem) {
            if (
                $lineItem->getProductId() === $productId
                && $lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE
            ) {
                $quantity = $lineItem->getQuantity();
                /** @var PickwareErpPickwareOrderLineItemEntity $pickwareOrderLineItem */
                $pickwareOrderLineItem = $lineItem->getExtension('pickwareErpPickwareOrderLineItem');
                if ($pickwareOrderLineItem !== null) {
                    $externallyFulfilledQuantity = $pickwareOrderLineItem->getExternallyFulfilledQuantity() ?? 0;
                    $quantity = max(0, $quantity - $externallyFulfilledQuantity);
                }

                $orderQuantity += $quantity;
            }
        }

        return $orderQuantity;
    }
}
