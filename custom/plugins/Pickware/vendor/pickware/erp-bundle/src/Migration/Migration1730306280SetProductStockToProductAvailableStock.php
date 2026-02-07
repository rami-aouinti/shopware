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

class Migration1730306280SetProductStockToProductAvailableStock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730306280;
    }

    public function update(Connection $connection): void
    {
        // check if column `available_stock` exists in table `product`
        $columnExists = $connection->fetchOne(
            'SELECT 1
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE
                `TABLE_NAME` = "product"
                AND `COLUMN_NAME` = "available_stock"
                AND `table_schema` = DATABASE();',
        );
        if (!$columnExists) {
            return;
        }

        $connection->executeStatement('UPDATE `product` SET `stock` = `available_stock`');
    }

    public function updateDestructive(Connection $connection): void {}
}
