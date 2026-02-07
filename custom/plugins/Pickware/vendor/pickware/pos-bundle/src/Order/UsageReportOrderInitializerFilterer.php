<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Order;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\PickwarePosBundle;
use Pickware\UsageReportBundle\Model\UsageReportOrderType;
use Pickware\UsageReportBundle\OrderReport\UsageReportOrderIdFilterEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsageReportOrderInitializerFilterer implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UsageReportOrderIdFilterEvent::class => 'filterOrderIdsByType',
        ];
    }

    public function filterOrderIdsByType(UsageReportOrderIdFilterEvent $event): void
    {
        $orderTypeCollection = $event->getOrderTypeCollection();
        $orderIds = $orderTypeCollection->getOrderIdsByType(UsageReportOrderType::Regular);

        $pickwarePosOrderIds = $this->entityManager->findIdsBy(
            OrderDefinition::class,
            [
                'id' => $orderIds,
                'salesChannel.typeId' => PickwarePosBundle::SALES_CHANNEL_TYPE_ID,
            ],
            $event->getContext(),
        );
        $orderTypeCollection->setOrderType(UsageReportOrderType::PickwarePos, $pickwarePosOrderIds);
    }
}
