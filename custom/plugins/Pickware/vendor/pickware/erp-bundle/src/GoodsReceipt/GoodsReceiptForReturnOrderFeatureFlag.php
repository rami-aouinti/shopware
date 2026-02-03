<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Pickware\FeatureFlagBundle\FeatureFlag;
use Pickware\FeatureFlagBundle\FeatureFlagType;

/**
 * @deprecated Will be removed in 3.0.0. No replacement.
 * This feature flag was accidentally accessed in the Pickware WMS plugin and therefore cannot be removed anymore.
 */
class GoodsReceiptForReturnOrderFeatureFlag extends FeatureFlag
{
    /**
     * @deprecated Will be removed in 3.0.0. No replacement.
     */
    public const NAME = 'pickware-erp.feature.goods-receipt-for-return-order';

    public function __construct()
    {
        parent::__construct(name: self::NAME, isActive: false, type: FeatureFlagType::Production);
    }
}
