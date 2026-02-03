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

class Migration1761120000AddPickingProcessLifecycleEventUserRoleSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1761120000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_picking_process_lifecycle_event_user_role` (
                `id` BINARY(16) NOT NULL,
                `picking_process_lifecycle_event_id` BINARY(16) NOT NULL,
                `user_role_reference_id` BINARY(16) NOT NULL,
                `user_role_snapshot` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `pw_wms_pick_proc_lifecycle_event_user_role.idx.user_role` (`user_role_reference_id`),
                CONSTRAINT `pw_wms_pick_proc_lifecycle_event_user_role.fk.event`
                    FOREIGN KEY (`picking_process_lifecycle_event_id`)
                    REFERENCES `pickware_wms_picking_process_lifecycle_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
