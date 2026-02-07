<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1761644270AddPickEventSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1761644270;
    }

    public function update(Connection $connection): void
    {
        // This database contains two generated columns; when creating the values while inserting, we can reach much
        // better performance when creating reports based on these columns.
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_pick_event` (
                `id` BINARY(16) NOT NULL,
                `product_reference_id` BINARY(16) NOT NULL,
                `product_snapshot` JSON NOT NULL,
                `product_weight` FLOAT DEFAULT NULL,
                `user_reference_id` BINARY(16) NOT NULL,
                `user_snapshot` JSON NOT NULL,
                `warehouse_reference_id` BINARY(16) NOT NULL,
                `warehouse_snapshot` JSON NOT NULL,
                `bin_location_reference_id` BINARY(16) NOT NULL,
                `bin_location_snapshot` JSON NOT NULL,
                `picking_process_reference_id` BINARY(16) NOT NULL,
                `picking_process_snapshot` JSON NOT NULL,
                `picking_mode` VARCHAR(255) NOT NULL,
                `picking_profile_reference_id` BINARY(16) NULL,
                `picking_profile_snapshot` JSON NULL,
                `picked_quantity` INT NOT NULL,
                `pick_created_at` DATETIME(3) NOT NULL,
                `pick_created_at_day` DATE GENERATED ALWAYS AS (DATE(`pick_created_at`)) STORED,
                `pick_created_at_hour` TINYINT GENERATED ALWAYS AS (HOUR(`pick_created_at`)) STORED,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `pickware_wms_pick_event.idx.product_reference_id` (`product_reference_id`),
                INDEX `pickware_wms_pick_event.idx.user_reference_id` (`user_reference_id`),
                INDEX `pickware_wms_pick_event.idx.warehouse_reference_id` (`warehouse_reference_id`),
                INDEX `pickware_wms_pick_event.idx.bin_location_reference_id` (`bin_location_reference_id`),
                INDEX `pickware_wms_pick_event.idx.picking_process_reference_id` (`picking_process_reference_id`),
                INDEX `pickware_wms_pick_event.idx.picking_profile_reference_id` (`picking_profile_reference_id`),
                INDEX `pickware_wms_pick_event.idx.pick_created_at` (`pick_created_at`),
                INDEX `pickware_wms_pick_event.idx.pick_created_at_day` (`pick_created_at_day`),
                INDEX `pickware_wms_pick_event.idx.pick_created_at_hour` (`pick_created_at_hour`),
                INDEX `pickware_wms_pick_event.idx.pick_created_at_day_hour` (`pick_created_at_day`, `pick_created_at_hour`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_pick_event_user_role` (
                `id` BINARY(16) NOT NULL,
                `pick_id` BINARY(16) NOT NULL,
                `user_role_reference_id` BINARY(16) NOT NULL,
                `user_role_snapshot` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `pickware_wms_pick_event_user_role.idx.user_role_reference_id` (`user_role_reference_id`),
                CONSTRAINT `pickware_wms_pick_event_user_role.fk.pick`
                    FOREIGN KEY (`pick_id`)
                    REFERENCES `pickware_wms_pick_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
