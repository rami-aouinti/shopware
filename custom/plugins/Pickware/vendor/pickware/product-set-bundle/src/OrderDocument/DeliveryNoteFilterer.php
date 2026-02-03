<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\OrderDocument;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Picking\OrderLineItemQuantity;
use Pickware\PickwareErpStarter\Picking\OrderLineItemQuantityCollection;
use Pickware\PickwareWms\DeliveryNote\DeliveryNoteFilterEvent;
use Pickware\PickwareWms\DeliveryNote\DeliveryNoteLineItemFilterEvent;
use Pickware\ProductSetBundle\Order\OrderUpdater;
use Pickware\ProductSetBundle\Order\Snapshot\ProductSetSnapshotBuilder;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeliveryNoteFilterer implements EventSubscriberInterface
{
    private const DELIVERY_NOTE_LINE_ITEM_FLATTENING_INDICATOR = 'pickwareProductSetBundleFlattenLineItemsOnDeliveryNote';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductSetSnapshotBuilder $productSetSnapshotBuilder,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryNoteLineItemFilterEvent::class => 'aggregateProductSetsInPartialDeliveryNote',
            DeliveryNoteFilterEvent::class => 'addProductSetMainProductToPartialDeliveryNote',
            DeliveryNoteOrdersEvent::class => [
                'handleLeftoverProductSetChildLineItems',
                -1000,
            ],
        ];
    }

    /**
     * When parts of a product set are shipped by wms this function determines if and how many times these parts can
     * be replaced/aggregated by/to their parent product aka the product set itself. If it is determined, that
     * full product sets are contained their children are deducted and the product set line items are added.
     *
     * Example:
     * ### Product Set
     *     * Product A x 2
     *     * Product B x 1
     *
     * ### Order Line Items
     *      * 2x Product Set
     *      * 4x Product A (Child of Product Set)
     *      * 2x Product B (Child of Product Set)
     *
     * ### Shipped Line Item Quantities ($event->getLineItemQuantities()) before function call
     *     * Product A x 3
     *     * Product B x2
     *
     * ### Shipped Line Item Quantities ($event->getLineItemQuantities()) after function call
     *     * Product A x 1
     *     * Product B x 1
     *     * Product Set
     */
    public function aggregateProductSetsInPartialDeliveryNote(DeliveryNoteLineItemFilterEvent $event): void
    {
        $orderProductSets = $this->productSetSnapshotBuilder->getProductSetSnapshotsForOrder(
            $event->getOrderId(),
            $event->getContext(),
        );

        // @phpstan-ignore function.alreadyNarrowedType (Method does not exist in older class versions)
        if (method_exists($event, 'getLineItemQuantities')) {
            $lineItemQuantities = $event->getLineItemQuantities();
        } else {
            $countingMapData = [];
            foreach ($event->getOrderLineItemQuantities() as $orderLineItemQuantity) {
                $countingMapData[$orderLineItemQuantity->getOrderLineItemId()] = $orderLineItemQuantity->getQuantity();
            }
            $lineItemQuantities = new CountingMap($countingMapData);
        }

        $orderLineItemQuantities = $orderProductSets->replaceChildLineItemsWithParentLineItems($lineItemQuantities);

        // If there are product set child line items left after the aggregation process we need to indicate that
        // the delivery note template needs to render them flattened down
        if (
            count(array_filter(
                $orderLineItemQuantities->getKeys(),
                fn(string $lineItemId) => in_array($lineItemId, $orderProductSets->getChildLineItemIds(), true),
            )) > 0
        ) {
            $event->updateCustomFields([self::DELIVERY_NOTE_LINE_ITEM_FLATTENING_INDICATOR => true]);
        }

        // @phpstan-ignore function.alreadyNarrowedType (Method does not exist in older class versions)
        if (method_exists($event, 'getLineItemQuantities')) {
            $event->setOrderLineItemQuantities($orderLineItemQuantities);
        } else {
            // Old versions of the event do not allow setting a CountingMap, so we need to convert it to an
            // OrderLineItemQuantityCollection.
            $event->setOrderLineItemQuantities(new OrderLineItemQuantityCollection(
                $orderLineItemQuantities->mapToList(
                    fn(string $orderLineItemId, int $quantity) => new OrderLineItemQuantity($orderLineItemId, $quantity),
                ),
            ));
        }
    }

    /**
     * This subscriber function is deprecated and only here for backwards compatibility with WMS. Will be removed with product set 3.0.0.
     */
    public function addProductSetMainProductToPartialDeliveryNote(DeliveryNoteFilterEvent $event): void
    {
        // don't run this subscriber if aggregateProductSetsInPartialDeliveryNote has already been called
        if (class_exists(DeliveryNoteLineItemFilterEvent::class)) {
            return;
        }

        $filteredOrderLineItems = $event->getOrder()->getLineItems();
        $missingMainProductSetProductOrderLineItemIds = array_unique($filteredOrderLineItems
            ->filter(
                // Is product set sub product and missing its parent. See Pickware\ProductSetBundle\Order\OrderUpdater.
                fn(OrderLineItemEntity $orderLineItem) =>
                    array_key_exists('pickwareProductSetConfigurationSnapshot', $orderLineItem->getPayload())
                    && !in_array($orderLineItem->getParentId(), $filteredOrderLineItems->getIds()),
            )
            ->map(fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getParentId()));

        if (count($missingMainProductSetProductOrderLineItemIds) === 0) {
            return;
        }

        $criteria = OrderDocumentCriteriaFactory::create([$event->getOrder()->getId()]);
        /** @var OrderEntity $originalOrder */
        $originalOrder = $this->entityManager->getOneBy(OrderDefinition::class, $criteria, $event->getContext());
        foreach ($missingMainProductSetProductOrderLineItemIds as $missingMainProductSetProductOrderLineItemId) {
            $productSetMainProductOrderLineItem = $originalOrder->getLineItems()->get($missingMainProductSetProductOrderLineItemId);
            if (!$productSetMainProductOrderLineItem) {
                // The product set sub product order line item references a parent order line item that is not present
                // in the order anymore. Ignore it for this case.
                continue;
            }

            // Note that no modification is done. I.e. the quantity is the original order line item quantity.
            $event->getOrder()->getLineItems()->add($productSetMainProductOrderLineItem);
        }
    }

    /**
     * This resets the product numbers of line items which are children of product sets and are left over after
     * addProductSetMainProductToPartialDeliveryNote was called. Since in this case the line item will be displayed
     * on the delivery note we want for the shop owner to see the product number of the child product for easier identification.
     */
    public function handleLeftoverProductSetChildLineItems(DeliveryNoteOrdersEvent $event): void
    {
        foreach ($event->getOrders() as $order) {
            $orderProductIds = array_unique($order->getLineItems()->fmap(fn(OrderLineItemEntity $lineItem) => $lineItem->getProductId()));
            /** @var ProductCollection $orderProducts */
            $orderProducts = $this->entityManager->findBy(
                ProductDefinition::class,
                ['id' => $orderProductIds],
                $event->getContext(),
                ['name'],
            );

            $mappedLineItems = $order->getLineItems()->map(function(OrderLineItemEntity $lineItem) use ($orderProducts, $order) {
                if (
                    !array_key_exists(
                        OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY,
                        $lineItem->getPayload(),
                    )
                ) {
                    return $lineItem;
                }

                if (!$order->getCustomFieldsValue(DeliveryNoteFilterer::DELIVERY_NOTE_LINE_ITEM_FLATTENING_INDICATOR)) {
                    return null;
                }

                $product = $orderProducts->get($lineItem->getProductId());
                $lineItem->setLabel($product->getName() ?: $product->getTranslation('name'));

                return $lineItem;
            });

            $order->setLineItems(new OrderLineItemCollection(array_filter($mappedLineItems)));
        }
    }
}
