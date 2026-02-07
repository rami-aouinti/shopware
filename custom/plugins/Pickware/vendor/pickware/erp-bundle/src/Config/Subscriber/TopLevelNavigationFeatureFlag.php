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

use Pickware\FeatureFlagBundle\ProductionFeatureFlag;

/**
 * @deprecated Will be removed with pickware-erp-starter 5.0.0 since the config whether the pickware navigation items
 * should be shown top level is now controlled by a window property.
 */
class TopLevelNavigationFeatureFlag extends ProductionFeatureFlag
{
    public const NAME = 'pickware-erp.feature.top-level-navigation';

    public function __construct()
    {
        parent::__construct(self::NAME, isActiveOnPremises: false);
    }
}
