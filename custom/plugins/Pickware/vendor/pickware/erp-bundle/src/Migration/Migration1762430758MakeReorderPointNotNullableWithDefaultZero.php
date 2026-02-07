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

class Migration1762430758MakeReorderPointNotNullableWithDefaultZero extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1762430758;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `pickware_erp_pickware_product`
            SET `reorder_point` = 0
            WHERE `reorder_point` IS NULL
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_pickware_product`
            MODIFY COLUMN `reorder_point` INT(11) NOT NULL DEFAULT 0
        ');

        $connection->executeStatement('
            UPDATE `pickware_erp_product_warehouse_configuration`
            SET `reorder_point` = 0
            WHERE `reorder_point` IS NULL
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_product_warehouse_configuration`
            MODIFY COLUMN `reorder_point` INT(11) NOT NULL DEFAULT 0
        ');

        $connection->executeStatement('
            UPDATE `pickware_erp_product_stock_location_configuration`
            SET `reorder_point` = 0
            WHERE `reorder_point` IS NULL
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_product_stock_location_configuration`
            MODIFY COLUMN `reorder_point` INT(11) NOT NULL DEFAULT 0
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
