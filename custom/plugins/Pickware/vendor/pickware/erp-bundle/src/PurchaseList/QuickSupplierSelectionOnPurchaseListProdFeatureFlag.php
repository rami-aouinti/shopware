<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList;

use Pickware\FeatureFlagBundle\ProductionFeatureFlag;

class QuickSupplierSelectionOnPurchaseListProdFeatureFlag extends ProductionFeatureFlag
{
    public const NAME = 'pickware-erp.prod.quick-supplier-selection-on-purchase-list';

    public function __construct()
    {
        parent::__construct(name: self::NAME, isActiveOnPremises: false);
    }
}
