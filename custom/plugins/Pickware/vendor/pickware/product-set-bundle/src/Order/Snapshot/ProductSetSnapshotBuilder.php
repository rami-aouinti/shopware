<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Order\Snapshot;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Pickware\ProductSetBundle\Order\OrderUpdater;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;

class ProductSetSnapshotBuilder
{
    public function __construct(
        public readonly EntityManager $entityManager,
    ) {}

    public function getProductSetSnapshotsForOrder(string $orderId, Context $context): ProductSetSnapshotCollection
    {
        /** @var OrderLineItemCollection $orderLineItems */
        $orderLineItems = $this->entityManager->findBy(
            OrderLineItemDefinition::class,
            [
                'orderId' => $orderId,
                'type' => [
                    // The delivery note only contains products (and product sets, after the subscriber modified it),
                    // whereas a product set may also contain discount line items. When discounts or other line item
                    // types would be included here, the logic on call site would never find a subset because
                    // discounts are not listed on delivery notes.
                    // Therefore we only need to load product and product set line items here.
                    LineItem::PRODUCT_LINE_ITEM_TYPE,
                    ProductSetDefinition::LINE_ITEM_TYPE,
                ],
            ],
            $context,
        );

        $productSetParentOrderLineItems = array_unique(
            $orderLineItems
                ->fmap(
                    fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getType() === ProductSetDefinition::LINE_ITEM_TYPE ? $orderLineItem->getId() : null,
                ),
        );

        $productSetSnapshots = [];
        foreach ($productSetParentOrderLineItems as $productSetParentOrderLineItemId) {
            $productSetSnapshots[] = new ProductSetSnapshot(
                $productSetParentOrderLineItemId,
                new CountingMap(
                    $orderLineItems
                        ->filter(
                            fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getParentId() === $productSetParentOrderLineItemId && isset($orderLineItem->getPayload()[OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY]),
                        )->reduce(
                            fn(array $childOrderLineItemQuantities, OrderLineItemEntity $orderLineItem) => [
                                ...$childOrderLineItemQuantities,
                                $orderLineItem->getId() => $orderLineItem->getPayload()[OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY]['quantity'],
                            ],
                            [],
                        ),
                ),
            );
        }

        return ProductSetSnapshotCollection::create($productSetSnapshots);
    }
}
