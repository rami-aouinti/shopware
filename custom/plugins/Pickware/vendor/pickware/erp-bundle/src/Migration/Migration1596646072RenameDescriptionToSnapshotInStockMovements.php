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

class Migration1596646072RenameDescriptionToSnapshotInStockMovements extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1596646072;
    }

    public function update(Connection $db): void
    {
        // Convert to text to prevent possible data-loss for big snapshots
        // Change name to snapshot and set type to JSON
        $db->executeStatement(
            'ALTER TABLE `pickware_erp_stock_movement`
            CHANGE `source_description` `source_description` TEXT NULL DEFAULT NULL,
            CHANGE `destination_description` `destination_description` TEXT NULL DEFAULT NULL',
        );

        $db->transactional(function(Connection $db): void {
            // First assume that no snapshot can be re-generated
            $db->executeStatement(
                'UPDATE `pickware_erp_stock_movement`
                SET
                    `source_description` = JSON_OBJECT(
                        "location",
                        "unknown",
                        "description",
                        `source_description`
                    ),
                    `destination_description` = JSON_OBJECT(
                        "location",
                        "unknown",
                        "description",
                        `destination_description`
                    )',
            );

            // Now regenerate all snapshots, that can be regenerated
            $this->generateWarehouseSnapshots($db);
            $this->generateBinLocationSnapshots($db);
            $this->generateOrderDeliveryPositionSnapshots($db);
            $this->generateSpecialStockLocationSnapshots($db);
        });

        // Change name to snapshot and set type to JSON
        $db->executeStatement(
            'ALTER TABLE `pickware_erp_stock_movement`
            CHANGE `source_description` `source_location_snapshot` JSON NULL DEFAULT NULL,
            CHANGE `destination_description` `destination_location_snapshot` JSON NULL DEFAULT NULL',
        );
    }

    public function updateDestructive(Connection $db): void {}

    private function generateWarehouseSnapshots(Connection $db): void
    {
        $db->executeStatement(
            'UPDATE `pickware_erp_stock_movement` `stock_movement`
            INNER JOIN `pickware_erp_warehouse` `warehouse`
                ON `source_warehouse_id` = `warehouse`.`id`
            SET `source_description` = JSON_OBJECT(
                "code",
                `warehouse`.`code`,
                "name",
                `warehouse`.`name`
            )',
        );
        $db->executeStatement(
            'UPDATE `pickware_erp_stock_movement` `stock_movement`
            INNER JOIN `pickware_erp_warehouse` `warehouse`
                ON `destination_warehouse_id` = `warehouse`.`id`
            SET `destination_description` = JSON_OBJECT(
                "code",
                `warehouse`.`code`,
                "name",
                `warehouse`.`name`
            )',
        );
    }

    private function generateBinLocationSnapshots(Connection $db): void
    {
        $db->executeStatement(
            'UPDATE `pickware_erp_stock_movement` `stock_movement`
            INNER JOIN `pickware_erp_bin_location` `bin_location`
                ON `source_bin_location_id` = `bin_location`.`id`
            INNER JOIN `pickware_erp_warehouse` `warehouse`
                ON `bin_location`.`warehouse_id` = `warehouse`.`id`
            SET `source_description` =  JSON_OBJECT(
                "code",
                `bin_location`.`code`,
                "warehouseName",
                `warehouse`.`name`,
                "warehouseCode",
                `warehouse`.`code`
            )',
        );
        $db->executeStatement(
            'UPDATE `pickware_erp_stock_movement` `stock_movement`
            INNER JOIN `pickware_erp_bin_location` `bin_location`
                ON `destination_bin_location_id` = `bin_location`.`id`
            INNER JOIN `pickware_erp_warehouse` `warehouse`
                ON `bin_location`.`warehouse_id` = `warehouse`.`id`
            SET `destination_description` =  JSON_OBJECT(
                "code",
                `bin_location`.`code`,
                "warehouseName",
                `warehouse`.`name`,
                "warehouseCode",
                `warehouse`.`code`
            )',
        );
    }

    private function generateOrderDeliveryPositionSnapshots(Connection $db): void
    {
        $db->executeStatement(
            'UPDATE `pickware_erp_stock_movement` `stock_movement`
            INNER JOIN `order_delivery_position`
                ON `source_order_delivery_position_id` = `order_delivery_position`.`id`
                    AND `source_order_delivery_position_version_id` = `order_delivery_position`.`version_id`
            INNER JOIN `order_line_item`
                ON `order_delivery_position`.`order_line_item_id` = `order_line_item`.`id`
                    AND `order_delivery_position`.`order_line_item_version_id` = `order_line_item`.`version_id`
            INNER JOIN `order`
                ON `order_line_item`.`order_id` = `order`.`id`
                   AND `order_line_item`.`order_version_id` = `order`.`version_id`
            SET `source_description` =  JSON_OBJECT(
                "orderNumber",
                `order`.`order_number`,
                "orderLineItemPosition",
                `order_line_item`.`position`,
                "orderLineItemProductNumber",
                JSON_UNQUOTE(JSON_EXTRACT(`order_line_item`.`payload`,"$.productNumber"))
            )',
        );
        $db->executeStatement(
            'UPDATE `pickware_erp_stock_movement` `stock_movement`
            INNER JOIN `order_delivery_position`
                ON `destination_order_delivery_position_id` = `order_delivery_position`.`id`
                    AND `destination_order_delivery_position_version_id` = `order_delivery_position`.`version_id`
            INNER JOIN `order_line_item`
                ON `order_delivery_position`.`order_line_item_id` = `order_line_item`.`id`
                    AND `order_delivery_position`.`order_line_item_version_id` = `order_line_item`.`version_id`
            INNER JOIN `order`
                ON `order_line_item`.`order_id` = `order`.`id`
                   AND `order_line_item`.`order_version_id` = `order`.`version_id`
            SET `destination_description` =  JSON_OBJECT(
                "orderNumber",
                `order`.`order_number`,
                "orderLineItemPosition",
                `order_line_item`.`position`,
                "orderLineItemProductNumber",
                JSON_UNQUOTE(JSON_EXTRACT(`order_line_item`.`payload`,"$.productNumber"))
            )',
        );
    }

    private function generateSpecialStockLocationSnapshots(Connection $db): void
    {
        $db->executeStatement(
            'UPDATE `pickware_erp_stock_movement`
            SET `source_description` = NULL
            WHERE `source_location_type_technical_name` = "special_stock_location"',
        );
        $db->executeStatement(
            'UPDATE `pickware_erp_stock_movement`
            SET `destination_description` = NULL
            WHERE `destination_location_type_technical_name` = "special_stock_location"',
        );
    }
}
