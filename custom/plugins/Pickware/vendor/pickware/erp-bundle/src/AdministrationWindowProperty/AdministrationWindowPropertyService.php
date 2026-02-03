<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\AdministrationWindowProperty;

use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Config\GlobalPluginConfig;
use Pickware\PickwareErpStarter\Config\Subscriber\ShowTopLevelNavigationConfigurationOptionFeatureFlag;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AdministrationWindowPropertyService
{
    public const SYSTEM_CONFIG_KEY_SHOW_TOP_LEVEL_NAVIGATION_ENTRIES = GlobalPluginConfig::GLOBAL_PLUGIN_CONFIG_DOMAIN . '.showTopLevelNavigationEntries';

    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly SystemConfigService $systemConfigService,
    ) {}

    public function getAdministrationWindowProperties(): array
    {
        return [
            'showTopLevelNavigationEntries' => $this->getShowTopLevelNavigationEntries(),
        ];
    }

    private function getShowTopLevelNavigationEntries(): bool
    {
        if (!$this->featureFlagService->isActive(ShowTopLevelNavigationConfigurationOptionFeatureFlag::NAME)) {
            // The only way the feature flag to show the configuration option is disabled is that we are in the cloud.
            // In the cloud, the top level navigation entries are always shown.
            return true;
        }

        return $this->systemConfigService->get(self::SYSTEM_CONFIG_KEY_SHOW_TOP_LEVEL_NAVIGATION_ENTRIES) ?? false;
    }
}
