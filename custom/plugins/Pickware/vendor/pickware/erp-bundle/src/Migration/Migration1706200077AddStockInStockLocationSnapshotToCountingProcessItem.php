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

class Migration1706200077AddStockInStockLocationSnapshotToCountingProcessItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1706200077;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stocktaking_stocktake_counting_process_item`
            ADD COLUMN `stock_in_stock_location_snapshot` INT(11) NOT NULL DEFAULT 0 AFTER `quantity`',
        );

        // Reconstruct stock at counting process creation time for any active stocktake. Ignore completed stocktakes
        // as they are not relevant to update anymore.
        $connection->executeStatement(
            'UPDATE `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem

            INNER JOIN (SELECT
            
                MAX(countingProcessItem.id) AS countingProcessItemId,
                MAX(IFNULL(stock.quantity, 0))
                + SUM(
                  CASE WHEN (
                      # outgoing, counted in bin location
                      (countingProcess.`bin_location_id` IS NOT NULL AND
                      movement.source_location_type_technical_name = "bin_location" AND
                      movement.source_bin_location_id = countingProcess.`bin_location_id`)
                      OR
                      # outgoing, counted in unknown
                      (countingProcess.`bin_location_id` IS NULL AND
                      movement.source_location_type_technical_name = "warehouse" AND
                      movement.source_warehouse_id = stocktake.`warehouse_id`)
                  ) THEN
                  IFNULL(movement.quantity, 0)
                  ELSE 0 END
                )
                - SUM(
                  CASE WHEN (
                      # incoming, counted in bin location
                      (countingProcess.`bin_location_id` IS NOT NULL AND
                      movement.destination_location_type_technical_name = "bin_location" AND
                      movement.destination_bin_location_id = countingProcess.`bin_location_id`)
                      OR
                      # incoming, counted in unknown
                      (countingProcess.`bin_location_id` IS NULL AND
                      movement.destination_location_type_technical_name = "warehouse" AND
                      movement.destination_warehouse_id = stocktake.`warehouse_id`)
                  ) THEN
                  IFNULL(movement.quantity, 0)
                  ELSE 0 END
                ) AS stockInStockLocationAtTimeOfCounting
            
            FROM `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem
            
            INNER JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
            ON countingProcess.`id` = countingProcessItem.`counting_process_id`
            
            INNER JOIN `pickware_erp_stocktaking_stocktake` stocktake
            ON stocktake.`id` = countingProcess.`stocktake_id`
            
            LEFT JOIN `pickware_erp_stock` stock
            ON stock.`product_id` = countingProcessItem.`product_id`
            AND stock.`product_version_id` = countingProcessItem.`product_version_id`
            AND (
              # counted in bin location
              (countingProcess.`bin_location_id` IS NOT NULL AND
              stock.location_type_technical_name = "bin_location" AND
              stock.`bin_location_id` = countingProcess.`bin_location_id`)
              OR
              # counted in unknown
              (countingProcess.`bin_location_id` IS NULL AND
              stock.location_type_technical_name = "warehouse" AND
              stock.`warehouse_id` = stocktake.`warehouse_id`)
            )
            
            LEFT JOIN pickware_erp_stock_movement movement
            ON movement.`product_id` = countingProcessItem.`product_id`
            AND movement.`product_version_id` = countingProcessItem.`product_version_id`
            AND (
              # incoming, counted in bin location
              (countingProcess.`bin_location_id` IS NOT NULL AND
              movement.destination_location_type_technical_name = "bin_location" AND
              movement.destination_bin_location_id = countingProcess.`bin_location_id`)
              OR
              # incoming, counted in unknown
              (countingProcess.`bin_location_id` IS NULL AND
              movement.destination_location_type_technical_name = "warehouse" AND
              movement.destination_warehouse_id = stocktake.`warehouse_id`)
              OR
              # outgoing, counted in bin location
              (countingProcess.`bin_location_id` IS NOT NULL AND
              movement.source_location_type_technical_name = "bin_location" AND
              movement.source_bin_location_id = countingProcess.`bin_location_id`)
              OR
              # outgoing, counted in unknown
              (countingProcess.`bin_location_id` IS NULL AND
              movement.source_location_type_technical_name = "warehouse" AND
              movement.source_warehouse_id = stocktake.`warehouse_id`)
            )
            AND movement.`created_at` > countingProcessItem.`created_at`
            
            # Only fetch, and therefore update, stocktakes that are not completed yet
            WHERE stocktake.`completed_at` IS NULL
            
            GROUP BY countingProcessItem.`id`
            ) AS backtrackedStock
            ON backtrackedStock.countingProcessItemId = countingProcessItem.`id`
            
            SET countingProcessItem.`stock_in_stock_location_snapshot` = backtrackedStock.stockInStockLocationAtTimeOfCounting;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
