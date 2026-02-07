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

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ProductSalesUpdateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'pickware_erp_product_sales_update';
    }

    public static function getDefaultInterval(): int
    {
        // every 1 hour (in seconds)
        return 60 * 60;
    }
}
