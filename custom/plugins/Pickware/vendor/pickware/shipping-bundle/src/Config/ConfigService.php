<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getConfigForSalesChannel(string $configDomain, ?string $salesChannelId): Config
    {
        $config = $this->systemConfigService->getDomain($configDomain, $salesChannelId, true /* $inherit */);

        return new Config(
            $configDomain,
            self::stripDomainFromKeys($config, $configDomain),
        );
    }

    public function getCommonShippingConfigForSalesChannel(?string $salesChannelId): CommonShippingConfig
    {
        $config = $this->getConfigForSalesChannel(CommonShippingConfig::CONFIG_DOMAIN, $salesChannelId);

        return new CommonShippingConfig($config);
    }

    public function saveConfigForSalesChannel(
        Config $config,
        ?string $salesChannelId,
    ): void {
        $domain = $config->getConfigDomain();
        $this->saveConfigurationArrayForDomainAndSalesChannel($config->getRawConfig(), $domain, $salesChannelId);
    }

    private static function stripDomainFromKeys(array $array, string $domain): array
    {
        $arrayWithStrippedDomain = [];
        foreach ($array as $key => $value) {
            $arrayWithStrippedDomain[str_replace($domain . '.', '', $key)] = $value;
        }

        return $arrayWithStrippedDomain;
    }

    private function saveConfigurationArrayForDomainAndSalesChannel(
        array $configArray,
        string $domain,
        ?string $salesChannelId,
    ): void {
        foreach ($configArray as $key => $value) {
            $key = $domain . '.' . $key;
            $this->systemConfigService->set($key, $value, $salesChannelId);
        }
    }
}
