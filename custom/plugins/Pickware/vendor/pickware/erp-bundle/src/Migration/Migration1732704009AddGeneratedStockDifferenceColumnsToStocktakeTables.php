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

class Migration1732704009AddGeneratedStockDifferenceColumnsToStocktakeTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732704009;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stocktaking_stocktake_counting_process_item`
            ADD COLUMN `absolute_stock_difference_in_stock_location` INT GENERATED ALWAYS AS (`quantity` - `stock_in_stock_location_snapshot`)
            STORED AFTER `stock_in_stock_location_snapshot`
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stocktaking_stocktake_counting_process_item`
            ADD COLUMN `percentage_stock_difference_in_stock_location` INT GENERATED ALWAYS AS (
                IF(`stock_in_stock_location_snapshot` = 0, 0,
                    IF(`quantity` = 0, 100,
                        IF(`quantity` <= `stock_in_stock_location_snapshot`,
                            ((`quantity` - `stock_in_stock_location_snapshot`) / `stock_in_stock_location_snapshot` * -100),
                            0
                        )
                    )
                )
            ) STORED AFTER `stock_in_stock_location_snapshot`');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stocktaking_stocktake_product_summary`
            ADD COLUMN `percentage_stock_difference` INT GENERATED ALWAYS AS (
                IF(`counted_stock` = 0,
                    IF(`counted_stock` = 0 AND `absolute_stock_difference` = 0, 0, 100),
                    IF((`counted_stock` - `absolute_stock_difference`) = 0, 0,
                        100 - (`counted_stock` / (`counted_stock` - `absolute_stock_difference`) * 100))
                )
            ) STORED AFTER `absolute_stock_difference`');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stocktaking_stocktake_snapshot_item`
            RENAME COLUMN `stock_difference` TO `absolute_stock_difference`
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stocktaking_stocktake_snapshot_item`
            RENAME COLUMN `total_stock_difference` TO `absolute_total_stock_difference`
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stocktaking_stocktake_snapshot_item`
            ADD COLUMN `percentage_stock_difference` INT GENERATED ALWAYS AS (
                IF(`stock_location_stock` = 0, 0,
                    IF(`counted` = 0, 100,
                        IF(`counted` <= `stock_location_stock`,
                            `absolute_stock_difference` / `stock_location_stock` * -100, 
                            0
                        )
                    )
                )
            ) STORED AFTER `absolute_stock_difference`');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stocktaking_stocktake_snapshot_item`
            MODIFY COLUMN `absolute_stock_difference` INT GENERATED ALWAYS AS (`counted` - `stock_location_stock`) STORED
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stocktaking_stocktake_snapshot_item`
            MODIFY COLUMN `absolute_total_stock_difference` INT GENERATED ALWAYS AS (`total_counted` - `warehouse_stock`) STORED
        ');
    }
}
