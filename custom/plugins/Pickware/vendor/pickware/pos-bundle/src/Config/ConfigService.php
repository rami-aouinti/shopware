<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Config;

use Pickware\PickwarePos\PickwarePosBundle;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getPublicSalesChannelDomainId(?string $configSalesChannelId): ?string
    {
        /** @var array|null $posPublicSalesChannelAndDomainId */
        $posPublicSalesChannelAndDomainId = $this->systemConfigService->get(
            PickwarePosBundle::PLUGIN_CONFIG_DOMAIN . '.posPublicSalesChannelAndDomainId',
            $configSalesChannelId,
        );
        if (
            $posPublicSalesChannelAndDomainId === null
            || !$posPublicSalesChannelAndDomainId['salesChannelId']
            || !$posPublicSalesChannelAndDomainId['domainId']
        ) {
            return null;
        }

        return $posPublicSalesChannelAndDomainId['domainId'];
    }
}
