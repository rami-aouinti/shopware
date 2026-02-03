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

class Migration1668172780CreateStocktakingStocktakeSnapshotItemSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1668172780;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_erp_stocktaking_stocktake_snapshot_item` (
                `id` BINARY(16) NOT NULL,
                `counting_process_item_id` BINARY(16) NOT NULL,
                `warehouse_stock` INT(11) NOT NULL,
                `total_counted` INT(11) NOT NULL,
                `total_stock_difference` INT(11) NOT NULL,
                `stock_location_stock` INT(11) NOT NULL,
                `counted` INT(11) NOT NULL,
                `stock_difference` INT(11) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_staking_snapshot_item.uidx.counting_process_item` (`counting_process_item_id`),
                CONSTRAINT `pickware_erp_staking_snapshot_item.fk.counting_process_item`
                    FOREIGN KEY (`counting_process_item_id`)
                    REFERENCES `pickware_erp_stocktaking_stocktake_counting_process_item` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
