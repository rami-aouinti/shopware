<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Warehouse;

use Pickware\FeatureFlagBundle\PickwareFeatureFlagsFilterEvent;
use Pickware\PickwareErpStarter\Warehouse\WarehouseStockNotAvailableForSaleFeatureFlag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WarehouseStockNotAvailableForSaleFeatureFlagActivationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PickwareFeatureFlagsFilterEvent::class => [
                'activateWarehouseStockNotAvailableForSaleFeatureFlag',
                PickwareFeatureFlagsFilterEvent::PRIORITY_ON_PREMISES,
            ],
        ];
    }

    public function activateWarehouseStockNotAvailableForSaleFeatureFlag(PickwareFeatureFlagsFilterEvent $event): void
    {
        if (!class_exists(WarehouseStockNotAvailableForSaleFeatureFlag::class)) {
            return;
        }

        $event->getFeatureFlags()->getByName(WarehouseStockNotAvailableForSaleFeatureFlag::NAME)?->enable();
    }
}
