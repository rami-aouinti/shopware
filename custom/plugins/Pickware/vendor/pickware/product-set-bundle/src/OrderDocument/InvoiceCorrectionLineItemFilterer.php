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

use Pickware\PickwareErpStarter\InvoiceCorrection\Events\InvoiceCorrectionOrderEvent;
use Pickware\ProductSetBundle\Order\OrderUpdater;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvoiceCorrectionLineItemFilterer implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceCorrectionOrderEvent::class => 'filterOrderLineItems',
        ];
    }

    public function filterOrderLineItems(InvoiceCorrectionOrderEvent $event): void
    {
        $event->getOrder()->setLineItems($event->getOrder()->getLineItems()->filter(
            function(OrderLineItemEntity $lineItem): bool {
                if (
                    !array_key_exists(
                        OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY,
                        $lineItem->getPayload(),
                    )
                ) {
                    return true;
                }

                return FloatComparator::notEquals(($lineItem->getPrice()?->getTotalPrice() ?? 0.0), 0);
            },
        ));
    }
}
