<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Feature;

use Pickware\FeatureFlagBundle\ProductionFeatureFlag;

class PickwareErpProductionFeatureFlag extends ProductionFeatureFlag
{
    public const NAME = 'pickware-erp.prod.pickware-erp';

    public function __construct()
    {
        // This feature flag is per default active on-premises to be backwards compatible with the Pickware ERP Starter
        // and Pickware ERP Pro plugins, where it should always be active.
        // For the new "Pickware" plugin, the state of this feature flag will be controlled by the license bundle.
        parent::__construct(self::NAME, isActiveOnPremises: true);
    }
}
