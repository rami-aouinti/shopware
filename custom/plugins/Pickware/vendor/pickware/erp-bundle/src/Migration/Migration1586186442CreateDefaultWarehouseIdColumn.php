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

class Migration1586186442CreateDefaultWarehouseIdColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586186442;
    }

    public function update(Connection $db): void
    {
        $db->executeStatement(
            'ALTER TABLE `pickware_erp_config`
            ADD COLUMN `default_warehouse_id` BINARY(16) NULL',
        );

        $db->executeStatement(
            'UPDATE `pickware_erp_config`
            SET `default_warehouse_id`= (
                SELECT `id`
                FROM `pickware_erp_warehouse`
                ORDER BY `created_at`
                LIMIT 1
            )
            WHERE `default_warehouse_id` IS NULL',
        );

        $db->executeStatement(
            'ALTER TABLE `pickware_erp_config`
            CHANGE COLUMN `default_warehouse_id` `default_warehouse_id` BINARY(16) NOT NULL,
            ADD CONSTRAINT `fk.pickware_erp_config.default_warehouse_id`
                FOREIGN KEY (`default_warehouse_id`)
                REFERENCES `pickware_erp_warehouse` (`id`)
                ON UPDATE CASCADE
                ON DELETE RESTRICT',
        );
    }

    public function updateDestructive(Connection $db): void {}
}
