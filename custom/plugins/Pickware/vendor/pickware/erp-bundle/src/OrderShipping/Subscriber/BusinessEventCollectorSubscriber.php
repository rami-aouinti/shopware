<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping\Subscriber;

use InvalidArgumentException;
use Pickware\PickwareErpStarter\OrderShipping\Events\CompletelyShippedEvent;
use Pickware\PickwareErpStarter\OrderShipping\Events\PartiallyShippedEvent;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds the event to the flow trigger list and allows the configuration of actions
 * https://developer.shopware.com/docs/guides/plugins/plugins/framework/flow/add-flow-builder-trigger#add-your-new-event-to-the-flow-trigger-list
 */
class BusinessEventCollectorSubscriber implements EventSubscriberInterface
{
    private BusinessEventCollector $businessEventCollector;

    public function __construct(BusinessEventCollector $businessEventCollector)
    {
        $this->businessEventCollector = $businessEventCollector;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BusinessEventCollectorEvent::NAME => [
                [
                    'onAddCompletelyShippedEvent',
                    1000, // Adds the event before any other subscriber to prevent missing awareness or action
                ],
                [
                    'onAddPartialShippedEvent',
                    1000, // Adds the event before any other subscriber to prevent missing awareness or action
                ],
            ],
        ];
    }

    public function onAddCompletelyShippedEvent(BusinessEventCollectorEvent $event): void
    {
        $collection = $event->getCollection();

        $definition = $this->businessEventCollector->define(CompletelyShippedEvent::class);

        if (!$definition) {
            throw new InvalidArgumentException(sprintf(
                'Invalid business event in the class: %s',
                CompletelyShippedEvent::class,
            ));
        }

        $collection->set($definition->getName(), $definition);
    }

    public function onAddPartialShippedEvent(BusinessEventCollectorEvent $event): void
    {
        $collection = $event->getCollection();

        $definition = $this->businessEventCollector->define(PartiallyShippedEvent::class);

        if (!$definition) {
            throw new InvalidArgumentException(sprintf(
                'Invalid business event in the class: %s',
                PartiallyShippedEvent::class,
            ));
        }

        $collection->set($definition->getName(), $definition);
    }
}
