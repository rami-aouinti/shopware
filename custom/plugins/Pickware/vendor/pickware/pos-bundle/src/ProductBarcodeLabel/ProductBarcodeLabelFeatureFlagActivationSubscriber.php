<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\ProductBarcodeLabel;

use Pickware\FeatureFlagBundle\PickwareFeatureFlagsFilterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductBarcodeLabelFeatureFlagActivationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PickwareFeatureFlagsFilterEvent::class => [
                'activateProductBarcodeLabelFeatureFlag',
                PickwareFeatureFlagsFilterEvent::PRIORITY_ON_PREMISES,
            ],
        ];
    }

    public function activateProductBarcodeLabelFeatureFlag(PickwareFeatureFlagsFilterEvent $event): void
    {
        $event->getFeatureFlags()->getByName('pickware-erp.feature.product-barcode-label-creation')?->enable();
    }
}
