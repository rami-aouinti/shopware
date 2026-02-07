<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Pickware\FeatureFlagBundle\ProductionFeatureFlag;

class DisableProductReservedStockUpdaterFeatureFlag extends ProductionFeatureFlag
{
    // Note: After enabling this feature flag, the reserved and available stocks will be wrong!
    // Additionally, when the feature is disabled again, the reserved stock will not be fixed automatically.
    // You need to run the indexer manually in order to fix the stocks.
    public const NAME = 'pickware-erp.prod.disable-product-reserved-stock-updater';

    public function __construct()
    {
        parent::__construct(name: self::NAME, isActiveOnPremises: false);
    }
}
