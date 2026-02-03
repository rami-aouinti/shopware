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

use Pickware\ProductSetBundle\Order\OrderUpdater;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StornoLineItemFilterer implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            StornoOrdersEvent::class => 'filterOrderLineItems',
        ];
    }

    public function filterOrderLineItems(StornoOrdersEvent $event): void
    {
        $orders = $event->getOrders();

        foreach ($orders as $order) {
            $order->setLineItems(
                $order->getLineItems()->filter(
                    fn(OrderLineItemEntity $lineItem) => !array_key_exists(
                        OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY,
                        $lineItem->getPayload(),
                    ),
                ),
            );
        }
    }
}
