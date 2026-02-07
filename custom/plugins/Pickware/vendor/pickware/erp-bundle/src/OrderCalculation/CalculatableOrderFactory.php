<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderCalculation;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * A factory class to create CalculatableOrders and CalculatableOrderLineItems from order-like entities.
 */
class CalculatableOrderFactory
{
    // Return order should only be considered for order calculation (i.e. invoice correction) when they are approved.
    private const RETURN_ORDER_STATE_IGNORE_LIST = [
        ReturnOrderStateMachine::STATE_REQUESTED,
        ReturnOrderStateMachine::STATE_ANNOUNCED,
        ReturnOrderStateMachine::STATE_DECLINED,
        ReturnOrderStateMachine::STATE_CANCELLED,
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function createCalculatableOrderFromOrder(string $orderId, Context $context): CalculatableOrder
    {
        // To only fetch the order of the given version without a live-version-fallback, we need to add the version as a
        // filter manually.
        $filters = [
            'id' => $orderId,
            'versionId' => $context->getVersionId(),
        ];

        /** @var OrderEntity $orderEntity */
        $orderEntity = $this->entityManager->getOneBy(
            OrderDefinition::class,
            $filters,
            $context,
            ['lineItems.product'],
        );

        $order = new CalculatableOrder();
        $order->lineItems = array_values($orderEntity->getLineItems()->map(
            fn(OrderLineItemEntity $orderLineItemEntity) => $this->createOrderLineItemFromOrderLineItemEntity($orderLineItemEntity),
        ));
        $order->price = $orderEntity->getPrice();
        $order->shippingCosts = $orderEntity->getShippingCosts();

        return $order;
    }

    /**
     * Returns a CalculatableOrder for each ReturnOrder of the given Order. Return orders that are not approved yet
     * are filtered (see self::RETURN_ORDER_STATE_IGNORE_LIST).
     *
     * @return CalculatableOrder[]
     */
    public function createCalculatableOrdersFromReturnOrdersOfOrder(string $orderId, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(
                new EqualsFilter('orderId', $orderId),
                new NotFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsAnyFilter('state.technicalName', self::RETURN_ORDER_STATE_IGNORE_LIST),
                    ],
                ),
                // To only fetch return orders of the given version without a live-version-fallback, we need to add the
                // version as a filter manually.
                new EqualsFilter('versionId', $context->getVersionId()),
            )
            ->addSorting(new FieldSorting('createdAt'));

        /** @var ReturnOrderCollection $returnOrders */
        $returnOrders = $this->entityManager->findBy(
            ReturnOrderDefinition::class,
            $criteria,
            $context,
            ['lineItems.orderLineItem'],
        );

        return array_values($returnOrders->map(
            fn(ReturnOrderEntity $returnOrder) => $this->createCalculatableOrderFromReturnOrderEntity($returnOrder),
        ));
    }

    public function createCalculatableOrderFromReturnOrder(string $returnOrderId, Context $context): CalculatableOrder
    {
        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            ['lineItems.orderLineItem'],
        );

        return $this->createCalculatableOrderFromReturnOrderEntity($returnOrder);
    }

    private function createCalculatableOrderFromReturnOrderEntity(ReturnOrderEntity $returnOrder): CalculatableOrder
    {
        $order = new CalculatableOrder();
        $order->lineItems = array_values($returnOrder->getLineItems()->map(
            fn(ReturnOrderLineItemEntity $returnOrderLineItem) => $this->createOrderLineItemFromReturnOrderLineItemEntity($returnOrderLineItem),
        ));
        $order->price = $returnOrder->getPrice();
        $order->shippingCosts = $returnOrder->getShippingCosts() ?? new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        return $order;
    }

    private function createOrderLineItemFromOrderLineItemEntity(OrderLineItemEntity $orderLineItemEntity): CalculatableOrderLineItem
    {
        $orderLineItem = new CalculatableOrderLineItem();
        $orderLineItem->type = $orderLineItemEntity->getType();
        $orderLineItem->label = $orderLineItemEntity->getLabel();
        $orderLineItem->price = $orderLineItemEntity->getPrice();
        $orderLineItem->quantity = $orderLineItemEntity->getQuantity();
        $orderLineItem->productId = $orderLineItemEntity->getProductId();
        $orderLineItem->productVersionId = $orderLineItemEntity->getProduct()?->getVersionId();
        $orderLineItem->payload = $orderLineItemEntity->getPayload();
        $orderLineItem->position = $orderLineItemEntity->getPosition();
        $orderLineItem->singleOriginatingOrderLineItemId = $orderLineItemEntity->getId();

        return $orderLineItem;
    }

    private function createOrderLineItemFromReturnOrderLineItemEntity(
        ReturnOrderLineItemEntity $returnOrderLineItemEntity,
    ): CalculatableOrderLineItem {
        $orderLineItem = new CalculatableOrderLineItem();
        $orderLineItem->type = $returnOrderLineItemEntity->getType();
        $orderLineItem->label = $returnOrderLineItemEntity->getName();
        $orderLineItem->price = $returnOrderLineItemEntity->getPrice();
        $orderLineItem->quantity = $returnOrderLineItemEntity->getQuantity();
        $orderLineItem->productId = $returnOrderLineItemEntity->getProductId();
        $orderLineItem->productVersionId = $returnOrderLineItemEntity->getProductVersionId();
        if ($returnOrderLineItemEntity->getOrderLineItem()) {
            $orderLineItem->payload = $returnOrderLineItemEntity->getOrderLineItem()->getPayload();
        } else {
            // Minimal backup payload if the order line item does not exist anymore
            $orderLineItem->payload = [
                'productNumber' => $returnOrderLineItemEntity->getProductNumber(),
            ];
        }
        $orderLineItem->position = $returnOrderLineItemEntity->getPosition();
        $orderLineItem->singleOriginatingOrderLineItemId = $returnOrderLineItemEntity->getOrderLineItemId();

        return $orderLineItem;
    }
}
