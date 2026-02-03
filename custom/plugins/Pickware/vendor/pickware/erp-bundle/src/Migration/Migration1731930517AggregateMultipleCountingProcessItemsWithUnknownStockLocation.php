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

/**
 * Aggregates multiple counting process items in the unknown stock location for the same product for active stocktakes.
 *
 * With new features (https://github.com/pickware/shopware-plugins/issues/7568) we rely on the system to only have a
 * single counting process item per stock location - including the unknown stock location. New counting process items in
 * the unknown stock location will be merged into one. Existing items must be migrated.
 */
class Migration1731930517AggregateMultipleCountingProcessItemsWithUnknownStockLocation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731688007;
    }

    public function update(Connection $connection): void
    {
        // Update the quantity of counting process items with unknown stock location to the total quantity of all
        // counting process items with unknown stock location for the same stocktake and product.
        $connection->executeStatement('
            UPDATE pickware_erp_stocktaking_stocktake_counting_process_item item
            JOIN (
                SELECT
                    id,
                    total_quantity
                FROM (
                    SELECT
                        item.id,
                        item.product_id,
                        item.quantity,
                        ROW_NUMBER() OVER (PARTITION BY process.stocktake_id, item.product_id ORDER BY item.created_at DESC) AS row_num,
                        SUM(quantity) OVER (PARTITION BY stocktake_id, product_id) AS total_quantity
                    FROM pickware_erp_stocktaking_stocktake_counting_process_item item
                        JOIN pickware_erp_stocktaking_stocktake_counting_process process
                            ON item.counting_process_id = process.id
                        JOIN pickware_erp_stocktaking_stocktake stocktake
                            ON process.stocktake_id = stocktake.id
                         WHERE
                            process.bin_location_id IS NULL AND
                            process.bin_location_snapshot IS NULL AND
                            stocktake.is_active = 1
                     ) subquery
                WHERE subquery.row_num = 1
            ) grouped_items
            ON item.id = grouped_items.id
            SET item.quantity = grouped_items.total_quantity;');

        // Delete all counting process items with unknown stock location that are not the most recent counting process
        // item for the same stocktake and product. This way one item remains.
        $connection->executeStatement('
            DELETE FROM pickware_erp_stocktaking_stocktake_counting_process_item
            WHERE id IN (
                SELECT id FROM (
                    SELECT
                        item.id,
                        ROW_NUMBER() OVER (PARTITION BY process.stocktake_id, item.product_id ORDER BY item.created_at DESC) AS row_num
                    FROM pickware_erp_stocktaking_stocktake_counting_process_item item
                    JOIN pickware_erp_stocktaking_stocktake_counting_process process
                        ON item.counting_process_id = process.id
                    JOIN pickware_erp_stocktaking_stocktake stocktake
                        ON process.stocktake_id = stocktake.id
                    WHERE
                        process.bin_location_id IS NULL AND
                        process.bin_location_snapshot IS NULL AND
                        stocktake.is_active = 1
                ) subquery
                WHERE subquery.row_num > 1
            );');

        // Delete all counting processes with unknown stock location that do not have any counting process items left.
        $connection->executeStatement('
            DELETE FROM pickware_erp_stocktaking_stocktake_counting_process
            WHERE id IN (
                (
                SELECT * FROM
                    (
                    SELECT process.id
                    FROM pickware_erp_stocktaking_stocktake_counting_process process
                    LEFT JOIN pickware_erp_stocktaking_stocktake_counting_process_item item
                        ON process.id = item.counting_process_id
                    JOIN pickware_erp_stocktaking_stocktake stocktake
                        ON process.stocktake_id = stocktake.id
                    WHERE
                        item.id IS NULL AND
                        stocktake.is_active = 1 AND
                        process.bin_location_id IS NULL AND
                        process.bin_location_snapshot IS NULL
                    )
                as subquery)
            );');

        // We changed the grid UI, so a saved grid configuration (columns and sorting) would lead to unwanted consequences
        $connection->executeStatement(
            'DELETE FROM `user_config` WHERE `key` = "grid.setting.pw-erp-stocktaking-stocktake-counting-process-items-grid";',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
