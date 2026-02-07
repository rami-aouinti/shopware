<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\DefaultConfigValue;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

class DefaultConfigValueInstaller
{
    private readonly ConfigReader $configReader;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        ConfigReader $configReader = new ConfigReader(),
    ) {
        $this->configReader = $configReader;
    }

    /**
     * Fetches default values from bundle configuration and saves it to database.
     * This is a variation of the `savePluginConfiguration` of the `SystemConfigService` to support custom config
     * filenames. See {@link SystemConfigService::savePluginConfiguration()}.
     */
    public function writeDefaultConfiguration(
        Bundle $bundle,
        string $configFilename = 'global-plugin-config',
        bool $override = false,
    ): void {
        try {
            $config = $this->configReader->getConfigFromBundle($bundle, $configFilename);
        } catch (BundleConfigNotFoundException) {
            return;
        }

        $prefix = $bundle->getName() . '.' . $configFilename . '.';

        $this->systemConfigService->saveConfig($config, $prefix, $override);
    }
}
