<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DeliveryNote;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Order\Model\PickwareErpPickwareOrderLineItemEntity;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// Filters out line items from the delivery note that are completely externally fulfilled.
// For line items that are partially externally fulfilled, only the internally fulfilled quantity is printed on the
// delivery note.
class DeliveryNoteLineItemFilterer implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryNoteOrdersEvent::class => 'filterLineItemsFromEvent',
        ];
    }

    public function filterLineItemsFromEvent(DeliveryNoteOrdersEvent $event): void
    {
        $context = $event->getContext();
        /** @var OrderEntity $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            ['id' => $event->getOrders()->map(fn(OrderEntity $order) => $order->getId())],
            $context,
            ['lineItems.pickwareErpPickwareOrderLineItem'],
        );

        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            if (!array_key_exists($order->getId(), $event->getOperations())) {
                continue;
            }

            $operation = $event->getOperations()[$order->getId()];
            $customFields = $operation->getConfig()['custom'] ?? null;
            if ($customFields !== null && isset($customFields['pickwareWmsProductsInDelivery'])) {
                // If the delivery note is created by WMS, so only products that are in the picking process should be
                // printed on the delivery note. We do not filter anything in this case.
                continue;
            }

            $filteredOrderLineItemQuantities = new CountingMap();
            foreach ($order->getLineItems() as $orderLineItem) {
                $quantity = PickwareErpPickwareOrderLineItemEntity::getInternallyFulfilledQuantityFromOderLineItem($orderLineItem);
                $filteredOrderLineItemQuantities->add($orderLineItem->getId(), $quantity);
            }

            if ($filteredOrderLineItemQuantities->count() === 0) {
                // If the delivery note would be empty, we do not want to create it.
                // Since Shopware is not throwing the event in a try-catch block, we cannot throw an exception here and
                // inform the user of what went wrong, so we have to silently remove the order from the event.
                $event->getOrders()->remove($order->getId());

                continue;
            }

            $orderFromEvent = $event->getOrders()->get($order->getId());
            $orderLineItems = $orderFromEvent->getLineItems();
            $modifiedOrderLineItems = $orderLineItems
                ?->filter(fn(OrderLineItemEntity $orderLineItem) => $filteredOrderLineItemQuantities->has($orderLineItem->getId()))
                ?->map(
                    function(OrderLineItemEntity $orderLineItem) use ($filteredOrderLineItemQuantities) {
                        $orderLineItem->setQuantity(
                            $filteredOrderLineItemQuantities->get($orderLineItem->getId()),
                        );

                        return $orderLineItem;
                    },
                );

            $orderFromEvent->setLineItems(new OrderLineItemCollection($modifiedOrderLineItems));
        }
    }
}
