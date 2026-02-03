<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\OrderShipping;

use Pickware\FeatureFlagBundle\PickwareFeatureFlagsFilterEvent;
use Pickware\PickwareErpStarter\OrderShipping\ShipProductsAutomaticallyProdFeatureFlag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShipProductsAutomaticallyFeatureFlagActivationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PickwareFeatureFlagsFilterEvent::class => [
                'activateShipProductsAutomaticallyFeatureFlag',
                PickwareFeatureFlagsFilterEvent::PRIORITY_ON_PREMISES,
            ],
        ];
    }

    public function activateShipProductsAutomaticallyFeatureFlag(PickwareFeatureFlagsFilterEvent $event): void
    {
        if (!class_exists(ShipProductsAutomaticallyProdFeatureFlag::class)) {
            return;
        }

        $event->getFeatureFlags()->getByName(ShipProductsAutomaticallyProdFeatureFlag::NAME)?->enable();
    }
}
