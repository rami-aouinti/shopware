<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Flow\Subscriber;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BusinessEventCollectorFilterSubscriber implements EventSubscriberInterface
{
    private const ERP_BUILT_IN_BUSINESS_EVENTS = [
        'pickware_erp.order_shipping.completely_returned',
        'pickware_erp.order_shipping.completely_shipped',
        'pickware_erp.order_shipping.partially_returned',
        'pickware_erp.order_shipping.partially_shipped',
        'pickware_erp.reorder.reorder_mail',
    ];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [BusinessEventCollectorEvent::NAME => 'filterBusinessEvents'];
    }

    public function filterBusinessEvents(BusinessEventCollectorEvent $event): void
    {
        $enabledBusinessEventNamesEvent = new EnabledPickwareBusinessEventNameCollectorEvent(self::ERP_BUILT_IN_BUSINESS_EVENTS);
        $this->eventDispatcher->dispatch($enabledBusinessEventNamesEvent);

        /**
         * @var int|string $key
         */
        foreach ($event->getCollection() as $key => $businessEvent) {
            if (!str_contains($businessEvent->getName(), 'pickware')) {
                continue;
            }

            if (
                in_array(
                    $businessEvent->getName(),
                    $enabledBusinessEventNamesEvent->getEnabledBusinessEvents(),
                )
            ) {
                continue;
            }

            $event->getCollection()->remove($key);
        }
    }
}
