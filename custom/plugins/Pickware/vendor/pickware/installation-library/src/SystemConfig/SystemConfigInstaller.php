<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\SystemConfig;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class SystemConfigInstaller
{
    public function __construct(private readonly SystemConfigService $systemConfigService) {}

    public function createSystemConfigValueIfNotExist(SystemConfigDefaultValue $systemConfigDefaultValue): void
    {
        if ($this->systemConfigService->get($systemConfigDefaultValue->systemConfigKey) !== null) {
            return;
        }

        $this->systemConfigService->set(
            $systemConfigDefaultValue->systemConfigKey,
            $systemConfigDefaultValue->value,
        );
    }
}
