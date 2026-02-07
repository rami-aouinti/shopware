<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier;

use Pickware\FeatureFlagBundle\ProductionFeatureFlag;

class MultipleSuppliersPerProductProductionFeatureFlag extends ProductionFeatureFlag
{
    public const NAME = 'pickware-erp.prod.multiple-suppliers-per-product';

    public function __construct()
    {
        parent::__construct(name: self::NAME, isActiveOnPremises: false);
    }
}
