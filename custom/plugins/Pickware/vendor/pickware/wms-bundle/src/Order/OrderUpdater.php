<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Order;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\Order\Model\PickwareWmsOrderDefinition;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
        ];
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $order = $event->getOrder();
        $isSingleItemOrder = false;
        if ($order->getLineItems() !== null) {
            $productLineItems = $order->getLineItems()->filterByProperty('type', LineItem::PRODUCT_LINE_ITEM_TYPE);
            $isSingleItemOrder = count($productLineItems) === 1 && $productLineItems->first()->getQuantity() === 1;
        }

        $event->getContext()->scope(Context::SYSTEM_SCOPE, fn($context) => $this->entityManager->create(
            PickwareWmsOrderDefinition::class,
            [
                [
                    'orderId' => $order->getId(),
                    'orderVersionId' => $order->getVersionId(),
                    'isSingleItemOrder' => $isSingleItemOrder,
                ],
            ],
            $context,
        ));
    }
}
