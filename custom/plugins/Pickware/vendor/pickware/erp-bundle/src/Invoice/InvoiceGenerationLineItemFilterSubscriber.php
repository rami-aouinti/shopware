<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Invoice;

use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvoiceGenerationLineItemFilterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [InvoiceOrdersEvent::class => 'onInvoiceOrders'];
    }

    /**
     * We do not want to show line items with quantity zero on invoices. This can happen if a line item gets refunded
     * before any invoice gets created, for example through the shopify integration.
     */
    public function onInvoiceOrders(InvoiceOrdersEvent $event): void
    {
        foreach ($event->getOrders() as $order) {
            foreach ($order->getLineItems() as $key => $lineItem) {
                // remove all line items with quantity zero from the order document
                if ($lineItem->getQuantity() === 0) {
                    $order->getLineItems()->remove($key);
                }
            }
        }
    }
}
