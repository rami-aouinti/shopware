<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Order;

use Pickware\FeatureFlagBundle\ProductionFeatureFlag;

// Note: This feature should only be activated in Shopware 6.6.5.0 and above. For more details see:
// https://github.com/pickware/shopware-plugins/issues/8075
class OrderListIncreasedSelectionCountProductionFeatureFlag extends ProductionFeatureFlag
{
    public const NAME = 'pickware-erp.prod.order-list-increased-selection-count';

    public function __construct()
    {
        parent::__construct(name: self::NAME, isActiveOnPremises: false);
    }
}
