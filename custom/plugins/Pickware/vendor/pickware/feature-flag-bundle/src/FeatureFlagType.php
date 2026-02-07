<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\FeatureFlagBundle;

enum FeatureFlagType: String
{
    /**
     * Development feature flags only exist to hide a feature in production during development and can be safely removed
     * after the feature has been released.
     */
    case Development = 'development';

    /**
     * Production feature flags are used to hide a feature in production and can be toggled on and off by other plugins.
     */
    case Production = 'production';
}
