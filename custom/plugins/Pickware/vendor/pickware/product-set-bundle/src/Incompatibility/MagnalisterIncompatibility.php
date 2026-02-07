<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Incompatibility;

use Pickware\IncompatibilityBundle\Incompatibility\PluginIncompatibility;

class MagnalisterIncompatibility extends PluginIncompatibility
{
    public function __construct(
        private readonly string $configKey,
        private readonly string $configValue,
        array $translatedWarnings,
    ) {
        parent::__construct(
            conflictingPlugin: 'RedMagnalisterSW6',
            translatedWarnings: $translatedWarnings,
        );
    }

    public function getVerifierServiceName(): string
    {
        return MagnalisterIncompatibilityVerifier::class;
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    public function getConfigValue(): string
    {
        return $this->configValue;
    }
}
