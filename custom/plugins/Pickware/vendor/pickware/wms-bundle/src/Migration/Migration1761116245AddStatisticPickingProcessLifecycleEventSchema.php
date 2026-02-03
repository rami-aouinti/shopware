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

class Migration1761116245AddStatisticPickingProcessLifecycleEventSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1761116245;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_wms_picking_process_lifecycle_event` (
                    `id` BINARY(16) NOT NULL,
                    `event_technical_name` VARCHAR(255) NOT NULL,
                    `picking_process_reference_id` BINARY(16) NOT NULL,
                    `picking_process_snapshot` JSON NOT NULL,
                    `user_reference_id` BINARY(16) NULL,
                    `user_snapshot` JSON NULL,
                    `warehouse_reference_id` BINARY(16) NOT NULL,
                    `warehouse_snapshot` JSON NOT NULL,
                    `picking_mode` VARCHAR(255) NOT NULL,
                    `picking_profile_reference_id` BINARY(16) NULL,
                    `picking_profile_snapshot` JSON NULL,
                    `device_reference_id` BINARY(16) NULL,
                    `device_snapshot` JSON NULL,
                    `event_created_at` DATETIME(3) NOT NULL,
                    `event_created_at_day` DATE GENERATED ALWAYS AS (DATE(`event_created_at`)) STORED,
                    `event_created_at_hour` TINYINT GENERATED ALWAYS AS (HOUR(`event_created_at`)) STORED,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.pick_proc` (`picking_process_reference_id`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.user` (`user_reference_id`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.warehouse` (`warehouse_reference_id`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.profile` (`picking_profile_reference_id`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.device` (`device_reference_id`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.evt_created_at` (`event_created_at`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.evt_created_at_day` (`event_created_at_day`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.evt_created_at_hour` (`event_created_at_hour`),
                    INDEX `pw_wms_picking_process_lifecycle_event.idx.evt_created_day_hour` (`event_created_at_day`, `event_created_at_hour`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }
}
