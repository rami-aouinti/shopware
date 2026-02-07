<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Config\Subscriber;

use Pickware\FeatureFlagBundle\PickwareFeatureFlagsFilterEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated Will be removed with pickware-erp-starter 5.0.0 since the config whether the pickware navigation items
 * should be shown top level is now controlled by a window property.
 */
class EnableTopLevelAdministrationNavigationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SystemConfigService $systemConfigService) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PickwareFeatureFlagsFilterEvent::class => [
                'enableTopLevelNavigationFeatureFlag',
                PickwareFeatureFlagsFilterEvent::PRIORITY_MANUAL_OVERRIDES,
            ],
        ];
    }

    public function enableTopLevelNavigationFeatureFlag(PickwareFeatureFlagsFilterEvent $event): void
    {
        $this->systemConfigService->get('PickwareErpBundle.global-plugin-config.showTopLevelNavigationEntries');

        $isActive = $this->systemConfigService->get(
            'PickwareErpBundle.global-plugin-config.showTopLevelNavigationEntries',
        ) ?? false;
        $event
            ->getFeatureFlags()
            ->getByName(TopLevelNavigationFeatureFlag::NAME)
            ?->setIsActive($isActive);
    }
}
