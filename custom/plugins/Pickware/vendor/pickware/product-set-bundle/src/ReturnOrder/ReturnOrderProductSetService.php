<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\ReturnOrder;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ReturnOrder\Events\ReturnOrderNonPhysicalLineItemsAddedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\ProductSetBundle\Order\OrderUpdater;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReturnOrderProductSetService implements EventSubscriberInterface
{
    public const PICKWARE_PRODUCT_SET_EXCLUDE_FROM_AUTO_RETURN_ORDER_PAYLOAD_KEY = 'pickwareProductSetExcludeFromAutoReturnOrderAssignment';

    public function __construct(private readonly EntityManager $entityManager) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ReturnOrderNonPhysicalLineItemsAddedEvent::class => 'addProductSetProductToReturn',
        ];
    }

    /*
     * Product set products cannot be returned via WMS, even when all assigned products of the product set are
     * returned. Therefore, we check if at least one product set product can be returned by the already returned
     * quantities of the assigned products.
     *
     * Further information: https://github.com/pickware/shopware-plugins/issues/5077
     */
    public function addProductSetProductToReturn(ReturnOrderNonPhysicalLineItemsAddedEvent $event): void
    {
        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $event->getReturnOrderId(),
            $event->getContext(),
            [
                'lineItems.orderLineItem',
                'order.lineItems',
            ],
        );

        $calculatedReturnOrderLineItemsQuantitiesForProductSetProduct = $returnOrder->getLineItems()
            ->filter(
                function(ReturnOrderLineItemEntity $returnOrderLineItem) {
                    if ($returnOrderLineItem->getOrderLineItem()?->getPayload()[self::PICKWARE_PRODUCT_SET_EXCLUDE_FROM_AUTO_RETURN_ORDER_PAYLOAD_KEY] ?? false) {
                        return false;
                    }

                    if (!array_key_exists(OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY, $returnOrderLineItem->getOrderLineItem()?->getPayload() ?? [])) {
                        return false;
                    }

                    if (!array_key_exists('quantity', $returnOrderLineItem->getOrderLineItem()?->getPayload()[OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY])) {
                        return false;
                    }

                    return true;
                },
            )->map(function(ReturnOrderLineItemEntity $lineItem) {
                $assignedProductQuantity = $lineItem->getOrderLineItem()?->getPayload()[OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY]['quantity'];

                return [
                    'parentOrderLineItemId' => $lineItem->getOrderLineItem()->getParentId(),
                    'calculatedQuantityOfParentLineItem' => (int) (floor($lineItem->getQuantity() / $assignedProductQuantity)),
                ];
            });

        $parentQuantitiesByParentId = [];
        foreach ($calculatedReturnOrderLineItemsQuantitiesForProductSetProduct as $calculatedQuantityOfProductSetProduct) {
            $parentLineItemId = $calculatedQuantityOfProductSetProduct['parentOrderLineItemId'];
            $expectedQuantity = $calculatedQuantityOfProductSetProduct['calculatedQuantityOfParentLineItem'];

            if (!array_key_exists($parentLineItemId, $parentQuantitiesByParentId)) {
                $parentQuantitiesByParentId[$parentLineItemId] = PHP_INT_MAX;
            }

            $parentQuantitiesByParentId[$parentLineItemId] = min(
                $parentQuantitiesByParentId[$parentLineItemId],
                $expectedQuantity,
            );
        }

        $returnOrderLineItemUpsertPayloads = [];
        foreach ($parentQuantitiesByParentId as $parentLineItemId => $quantity) {
            if ($quantity === 0) {
                continue;
            }

            $existingParentReturnOrderLineItem = $returnOrder->getLineItems()
                ->filter(
                    fn(ReturnOrderLineItemEntity $returnOrderLineItem) => $returnOrderLineItem->getOrderLineItemId() === $parentLineItemId,
                )->first();
            $parentOrderLineItem = $returnOrder->getOrder()->getLineItems()->get($parentLineItemId);

            if ($existingParentReturnOrderLineItem !== null) {
                $returnOrderLineItemUpsertPayloads[] = [
                    'id' => $existingParentReturnOrderLineItem->getId(),
                    // Never decrease quantity for existing return order line items
                    'quantity' => max($existingParentReturnOrderLineItem->getQuantity(), $quantity),
                ];
            } else {
                $returnOrderLineItemUpsertPayloads[] = [
                    'id' => Uuid::randomHex(),
                    'returnOrderId' => $returnOrder->getId(),
                    'orderLineItemId' => $parentOrderLineItem->getId(),
                    'reason' => ReturnOrderLineItemDefinition::REASON_UNKNOWN,
                    'type' => $parentOrderLineItem->getType(),
                    'name' => $parentOrderLineItem->getLabel(),
                    'productId' => $parentOrderLineItem->getProductId(),
                    'productNumber' => $parentOrderLineItem->getPayload()['productNumber'] ?? null,
                    'quantity' => $quantity,
                    'price' => $parentOrderLineItem->getPrice(),
                    'priceDefinition' => $parentOrderLineItem->getPriceDefinition(),
                ];
            }
        }

        $this->entityManager->upsert(
            ReturnOrderLineItemDefinition::class,
            $returnOrderLineItemUpsertPayloads,
            $event->getContext(),
        );
    }
}
