<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\GoodsReceipt;

use Pickware\FeatureFlagBundle\PickwareFeatureFlagsFilterEvent;
use Pickware\PickwareWms\PickwareWmsBundle;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GoodsReceiptFeatureFlagConfigOverrideSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PickwareFeatureFlagsFilterEvent::class => [
                'toggleGoodsReceiptFeatureFlag',
                PickwareFeatureFlagsFilterEvent::PRIORITY_MANUAL_OVERRIDES,
            ],
        ];
    }

    public function toggleGoodsReceiptFeatureFlag(PickwareFeatureFlagsFilterEvent $event): void
    {
        $enableReturnOrderManagementInWMSApp = $this->systemConfigService->get(
            PickwareWmsBundle::GLOBAL_PLUGIN_CONFIG_DOMAIN . '.enableReturnOrderManagementInWMSApp',
        ) ?? true;

        /**
         * There are four possible scenarios:
         * 1. The feature flag is enabled by the current cloud plan and the user has not disabled it in the plugin configuration.
         * 2. The feature flag is enabled by the current cloud plan and the user has disabled it in the plugin configuration.
         * 3. The feature flag is disabled by the current cloud plan and the user has not disabled it in the plugin configuration.
         * 4. The feature flag is disabled by the current cloud plan and the user has disabled it in the plugin configuration.
         *
         * In the first case, the feature flag should be enabled, as this is the default state.
         *
         * In the second case, the feature flag should be disabled, as the user has explicitly disabled it.
         *
         * In the third and fourth case, the feature flag should be disabled as the config should not be shown and the user
         * should not be able to overrule the cloud license plan.
         */
        if (!$enableReturnOrderManagementInWMSApp) {
            $event->disable('pickware-wms.feature.goods-receipt-return-orders');
        }
    }
}
