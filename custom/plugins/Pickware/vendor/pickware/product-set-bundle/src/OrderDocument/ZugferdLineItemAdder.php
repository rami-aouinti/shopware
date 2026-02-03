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

use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdInvoiceItemAddedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ZugferdLineItemAdder implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'zugferd-item-added.' . ProductSetDefinition::LINE_ITEM_TYPE => 'addProductSetLineItem',
        ];
    }

    public function addProductSetLineItem(ZugferdInvoiceItemAddedEvent $event): void
    {
        $event->document->withProductLineItem(
            $event->lineItem,
            $event->parentPosition,
        );
    }
}
