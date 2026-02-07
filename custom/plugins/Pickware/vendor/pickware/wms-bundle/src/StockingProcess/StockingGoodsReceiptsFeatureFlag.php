<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess;

use Pickware\FeatureFlagBundle\ProductionFeatureFlag;
use Pickware\PickwareErpStarter\GoodsReceipt\Controller\GoodsReceiptController;

class StockingGoodsReceiptsFeatureFlag extends ProductionFeatureFlag
{
    public function __construct()
    {
        $isActiveOnPremises = class_exists(GoodsReceiptController::class)
            && method_exists(GoodsReceiptController::class, 'getGoodsReceiptStockingListDocument');

        parent::__construct(
            name: 'pickware-wms.feature.stocking-goods-receipts',
            isActiveOnPremises: $isActiveOnPremises,
        );
    }
}
