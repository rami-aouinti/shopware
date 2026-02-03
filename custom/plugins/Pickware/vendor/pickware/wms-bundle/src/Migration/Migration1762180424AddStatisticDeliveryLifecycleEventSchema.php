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

class Migration1762180424AddStatisticDeliveryLifecycleEventSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1762180424;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_wms_delivery_lifecycle_event` (
                    `id` BINARY(16) NOT NULL,
                    `event_technical_name` VARCHAR(255) NOT NULL,
                    `delivery_reference_id` BINARY(16) NOT NULL,
                    `user_reference_id` BINARY(16) NOT NULL,
                    `user_snapshot` JSON NOT NULL,
                    `order_reference_id` BINARY(16) NOT NULL,
                    `order_version_id` BINARY(16) NOT NULL,
                    `order_snapshot` JSON NOT NULL,
                    `picking_process_reference_id` BINARY(16) NOT NULL,
                    `picking_process_snapshot` JSON NOT NULL,
                    `picking_mode` VARCHAR(255) NOT NULL,
                    `picking_profile_reference_id` BINARY(16) NULL,
                    `picking_profile_snapshot` JSON NULL,
                    `device_reference_id` BINARY(16) NULL,
                    `device_snapshot` JSON NULL,
                    `warehouse_reference_id` BINARY(16) NOT NULL,
                    `warehouse_snapshot` JSON NOT NULL,
                    `event_created_at` DATETIME(3) NOT NULL,
                    `event_created_at_day` DATE GENERATED ALWAYS AS (DATE(`event_created_at`)) STORED,
                    `event_created_at_hour` TINYINT GENERATED ALWAYS AS (HOUR(`event_created_at`)) STORED,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.delivery` (`delivery_reference_id`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.user` (`user_reference_id`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.order` (`order_reference_id`, `order_version_id`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.picking_process` (`picking_process_reference_id`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.picking_profile` (`picking_profile_reference_id`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.device` (`device_reference_id`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.warehouse` (`warehouse_reference_id`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.evt_created_at` (`event_created_at`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.evt_created_at_day` (`event_created_at_day`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.evt_created_at_hour` (`event_created_at_hour`),
                    INDEX `pw_wms_delivery_lifecycle_event.idx.evt_created_day_hour` (`event_created_at_day`, `event_created_at_hour`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_delivery_lifecycle_event_user_role` (
                `id` BINARY(16) NOT NULL,
                `delivery_lifecycle_event_id` BINARY(16) NOT NULL,
                `user_role_reference_id` BINARY(16) NOT NULL,
                `user_role_snapshot` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `pw_wms_delivery_lifecycle_event_user_role.idx.user_role` (`user_role_reference_id`),
                CONSTRAINT `pw_wms_delivery_lifecycle_event_user_role.fk.event`
                    FOREIGN KEY (`delivery_lifecycle_event_id`)
                    REFERENCES `pickware_wms_delivery_lifecycle_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }
}
