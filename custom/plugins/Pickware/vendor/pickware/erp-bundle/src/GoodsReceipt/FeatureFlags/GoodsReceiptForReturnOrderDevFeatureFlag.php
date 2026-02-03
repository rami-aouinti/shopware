<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags;

use Pickware\FeatureFlagBundle\DevelopmentFeatureFlag;

/**
 * Removing this FF after release requires a great cleanup. We need to pay attention that after activating this FF,
 * return orders cannot be used as a stock location anymore but the code is still there.
 */
class GoodsReceiptForReturnOrderDevFeatureFlag extends DevelopmentFeatureFlag
{
    public const NAME = 'pickware-erp.dev.goods-receipt-for-return-order';

    public function __construct()
    {
        parent::__construct(name: self::NAME, isFeatureReleased: false);
    }
}
