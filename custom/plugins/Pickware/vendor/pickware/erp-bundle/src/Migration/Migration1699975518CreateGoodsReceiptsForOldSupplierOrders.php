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
use Doctrine\DBAL\Schema\Column;
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1699975518CreateGoodsReceiptsForOldSupplierOrders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699975518;
    }

    public function update(Connection $connection): void
    {
        // In order to add entries in the supplier_order_goods_receipt_mapping table after we have created
        // the goods receipts, we need to "remember" to which supplier order we have created the goods receipt.
        // For that, we add a temporary column that we will drop again at the end of this migration.

        // There was a bug in the `migrateOldStockMovements` function which caused this migration to fail for some. We
        // fixed the function. But this first statement must be made idempotent, so this migration can be retried. See
        // also: https://github.com/pickware/shopware-plugins/issues/5202
        $goodsReceiptTableColumnNames = array_map(
            fn(Column $column) => $column->getName(),
            $connection->createSchemaManager()->listTableColumns('pickware_erp_goods_receipt'),
        );
        if (!in_array('supplier_order_id', $goodsReceiptTableColumnNames)) {
            $connection->executeStatement(
                'ALTER TABLE `pickware_erp_goods_receipt`
            ADD COLUMN `supplier_order_id` BINARY(16) NULL',
            );
        }

        $connection->transactional(function(Connection $connection): void {
            $this->createGoodsReceiptsForOldSupplierOrders($connection);
            $this->migrateOldStockMovements($connection);
            $this->removeSupplierOrderAsStockLocation($connection);
        });

        // Remove the location type from stock movements
        // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
        // (false positive) We drop the respective column (and in turn, the index) right after. The column just has another name.
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_stock_movement`
                DROP FOREIGN KEY `pickware_erp_stock_movement.fk.source_supplier_order`,
                DROP FOREIGN KEY `pickware_erp_stock_movement.fk.dest_supplier_order`,
                DROP COLUMN `source_supplier_order_id`,
                DROP COLUMN `destination_supplier_order_id`
                SQL
        );

        // Remove the location type from stock
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_stock`
                DROP INDEX `pickware_erp_stock.uidx.product.supplier_order`,
                DROP FOREIGN KEY `pickware_erp_stock.fk.supplier_order`,
                DROP COLUMN `supplier_order_id`
                SQL
        );

        // We drop the temporary column here that we inserted at the beginning
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_goods_receipt`
                DROP COLUMN `supplier_order_id`
                SQL
        );
    }

    public function updateDestructive(Connection $connection): void {}

    private function createGoodsReceiptsForOldSupplierOrders(Connection $connection): void
    {
        // We will create the goods receipts as 'completed'
        $completedStateId = $connection->fetchOne(
            'SELECT
                LOWER(HEX(`state_machine_state`.`id`))
            FROM `state_machine_state`
            INNER JOIN `state_machine` ON `state_machine_state`.`state_machine_id` = `state_machine`.`id`
            WHERE `state_machine_state`.`technical_name` = "completed"
                AND `state_machine`.`technical_name` = "pickware_erp_goods_receipt.state"',
        );

        $generateUuid = SqlUuid::UUID_V4_GENERATION;
        // We create goods receipts for all supplier orders that have been stocked using the old handling
        // (by directly moving stock through them).
        // Note that the created-at property is currently UTC_TIMESTAMP(3). If will be updated in migrateOldStockMovements().
        $query = <<<SQL
            INSERT INTO `pickware_erp_goods_receipt` (
                `id`,
                `type`,
                `state_id`,
                `number`,
                `currency_id`,
                `currency_factor`,
                `item_rounding`,
                `total_rounding`,
                `price`,
                `user_snapshot`,
                `warehouse_id`,
                `warehouse_snapshot`,
                `supplier_id`,
                `supplier_snapshot`,
                `supplier_order_id`
            ) SELECT
                {$generateUuid},
                "supplier",
                UNHEX(:completedStateId),
                # We cannot retrospectively add numbers to the number range (as it is continuous). Use a random number
                # (parts of the supplier order id) instead. Add a "0" prefix, so these goods receipts are always listed
                # _after_ other goods receipts, that use the number range, if sorted alphanumerically. This is actually
                # correct as these migration goods receipts did happen "before" we actually released goods receipts.
                CONCAT("0", LEFT(LOWER(HEX(`supplierOrder`.`id`)), 9)),
                `supplierOrder`.`currency_id`,
                `currency`.`factor`,
                `supplierOrder`.`item_rounding`,
                `supplierOrder`.`total_rounding`,
                `supplierOrder`.`price`,
                JSON_OBJECT(
                    'username', 'unknown',
                    'firstName', 'unknown',
                    'lastName', 'unknown',
                    'email', 'unknown'
                ),
                `supplierOrder`.`warehouse_id`,
                JSON_OBJECT(
                    'name', `warehouse`.`name`,
                    'code', `warehouse`.`code`
                ),
                `supplierOrder`.`supplier_id`,
                JSON_OBJECT(
                    'name', `supplier`.`name`,
                    'number', `supplier`.`number`
                ),
                `supplierOrder`.`id`
            FROM `pickware_erp_supplier_order` AS `supplierOrder`
            INNER JOIN `pickware_erp_stock_movement` AS `stockMovement`
                ON (`supplierOrder`.`id` = `stockMovement`.`destination_supplier_order_id`
                    OR `supplierOrder`.`id` = `stockMovement`.`source_supplier_order_id`)
            LEFT JOIN `currency`
                ON `supplierOrder`.`currency_id` = `currency`.`id`
            LEFT JOIN `pickware_erp_warehouse` AS `warehouse`
                ON `supplierOrder`.`warehouse_id` = `warehouse`.`id`
            LEFT JOIN `pickware_erp_supplier` as `supplier`
                ON `supplierOrder`.`supplier_id` = `supplier`.`id`
            GROUP BY `supplierOrder`.`id`
            SQL;

        $connection->executeStatement(
            $query,
            ['completedStateId' => $completedStateId],
        );

        // Add the newly created goods receipts to the mapping table
        $connection->executeStatement(
            <<<SQL
                INSERT INTO `pickware_erp_supplier_order_goods_receipt_mapping` (
                    `supplier_order_id`,
                    `goods_receipt_id`
                ) SELECT
                    `goodsReceipt`.`supplier_order_id`,
                    `goodsReceipt`.`id`
                FROM `pickware_erp_goods_receipt` AS `goodsReceipt`
                    WHERE `goodsReceipt`.`supplier_order_id` IS NOT NULL
                SQL
        );

        $connection->executeStatement(
            <<<SQL
                INSERT INTO pickware_erp_goods_receipt_line_item (
                    `id`,
                    `goods_receipt_id`,
                    `quantity`,
                    `product_id`,
                    `product_version_id`,
                    `product_snapshot`,
                    `price`,
                    `price_definition`,
                    `supplier_order_id`
                ) SELECT
                    {$generateUuid},
                    `goodsReceipt`.`id`,
                    `supplierOrderLineItem`.`quantity`,
                    `supplierOrderLineItem`.`product_id`,
                    `supplierOrderLineItem`.`product_version_id`,
                    `supplierOrderLineItem`.`product_snapshot`,
                    `supplierOrderLineItem`.`price`,
                    `supplierOrderLineItem`.`price_definition`,
                    `supplierOrderLineItem`.`supplier_order_id`
                FROM `pickware_erp_supplier_order_line_item` AS `supplierOrderLineItem`
                INNER JOIN `pickware_erp_goods_receipt` AS `goodsReceipt`
                    ON `supplierOrderLineItem`.`supplier_order_id` = `goodsReceipt`.`supplier_order_id`
                SQL
        );
    }

    private function migrateOldStockMovements(Connection $connection): void
    {
        // For stock movements from or to supplier orders which have been deleted, there is no new goods receipt to
        // migrate the stock movements into.
        // We move stock from "a deleted goods receipt" into the same destination when a source supplier does not exist
        // anymore (was deleted). We move stock into "a deleted goods receipt" from the same source when a destination
        // supplier order does not exist anymore (was deleted).
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_stock_movement` AS `stockMovement`
                SET
                    `source_location_type_technical_name` = IF(
                        `stockMovement`.`source_supplier_order_id` IS NULL,
                        'goods_receipt',
                        `stockMovement`.`source_location_type_technical_name`
                    ),
                    `destination_location_type_technical_name` = IF(
                        `stockMovement`.`destination_supplier_order_id` IS NULL,
                        'goods_receipt',
                        `stockMovement`.`destination_location_type_technical_name`
                    )
                WHERE (`source_location_type_technical_name` = 'supplier_order' AND `source_supplier_order_id` IS NULL)
                   OR (`destination_location_type_technical_name` = 'supplier_order' AND `destination_supplier_order_id` IS NULL)
                SQL
        );

        // All these stock movements either moved stock from unknown into a supplier order, or from a supplier order
        // into a warehouse. We need to change the supplier order references to goods receipt references.
        // In order for the INSERT INTO ON DUPLICATE KEY UPDATE method to work, we need to insert all required fields.
        // The INNER JOINS ensure that stock movements are migrated for supplier orders that are still referenced by id
        // (so the referenced entity still exists).
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_stock_movement` AS `stockMovement`
                INNER JOIN `pickware_erp_supplier_order` AS `supplierOrder`
                    ON (`stockMovement`.`source_supplier_order_id` = `supplierOrder`.`id`
                        OR `stockMovement`.`destination_supplier_order_id` = `supplierOrder`.`id`)
                INNER JOIN `pickware_erp_goods_receipt` AS `goodsReceipt`
                    ON `goodsReceipt`.`supplier_order_id` = `supplierOrder`.`id`
                SET
                    # Set the goods receipt created-at timestamp to the (any) timestamp of the respective stock movement
                    # So the goods receipt is in the correct chronological order. The goods receipt created-at timestamp
                    # is relevant for the rated stock analysis. Therefore, we cannot use the UTC_TIMESTAMP(3) time of this
                    # migration.
                    `goodsReceipt`.`created_at` = `stockMovement`.`created_at`,
                    `source_location_type_technical_name` = IF(
                        `stockMovement`.`source_supplier_order_id` IS NULL,
                        'special_stock_location',
                        'goods_receipt'
                    ),
                    `source_supplier_order_id` = NULL,
                    `source_goods_receipt_id` = IF(
                        `stockMovement`.`source_supplier_order_id` IS NULL,
                        NULL,
                        `goodsReceipt`.`id`
                    ),
                    `source_location_snapshot` = IF(
                        `stockMovement`.`source_supplier_order_id` IS NULL,
                        `stockMovement`.`source_location_snapshot`,
                        JSON_OBJECT('number', `goodsReceipt`.`number`)
                    ),
                    `destination_location_type_technical_name` = IF(
                        `stockMovement`.`destination_supplier_order_id` IS NULL,
                        'warehouse',
                        'goods_receipt'
                    ),
                    `destination_supplier_order_id` = NULL,
                    `destination_goods_receipt_id` = IF(
                        `stockMovement`.`destination_supplier_order_id` IS NULL,
                        NULL,
                        `goodsReceipt`.`id`
                    ),
                    `destination_location_snapshot` = IF(
                        `stockMovement`.`destination_supplier_order_id` IS NULL,
                        `stockMovement`.`destination_location_snapshot`,
                        JSON_OBJECT('number', `goodsReceipt`.`number`)
                    )
                SQL
        );
    }

    private function removeSupplierOrderAsStockLocation(Connection $connection): void
    {
        // Remove all stock that is currently in a supplier order
        // (the normal workflow of restocking a supplier order should not leave any stock in a supplier order)
        $connection->executeStatement(
            <<<SQL
                DELETE FROM `pickware_erp_stock`
                WHERE `location_type_technical_name` = 'supplier_order'
                SQL
        );

        // Remove the location type "supplier_order"
        $connection->executeStatement(
            <<<SQL
                DELETE FROM `pickware_erp_location_type`
                WHERE `technical_name` = 'supplier_order'
                SQL
        );
    }
}
