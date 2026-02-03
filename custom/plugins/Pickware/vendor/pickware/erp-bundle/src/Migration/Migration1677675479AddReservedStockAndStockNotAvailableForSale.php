<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1677675479AddReservedStockAndStockNotAvailableForSale extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1677675479;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_pickware_product`
            ADD COLUMN `reserved_stock` INT(11) NOT NULL DEFAULT 0 AFTER `incoming_stock`,
            ADD COLUMN `stock_not_available_for_sale` INT(11) NOT NULL DEFAULT 0 AFTER `reserved_stock`
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
