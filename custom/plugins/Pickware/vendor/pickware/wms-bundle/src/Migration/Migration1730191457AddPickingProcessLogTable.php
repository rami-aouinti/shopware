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

class Migration1730191457AddPickingProcessLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730191457;
    }

    public function update(Connection $connection): void
    {
        // No foreign key constraint so the order or user can be deleted.
        $connection->executeStatement(
            'CREATE TABLE `pickware_wms_picking_process_log` (
                `id` BINARY(16) NOT NULL,
                `picking_process_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `event_name` VARCHAR(255) NOT NULL,
                `payload` JSON NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_wms_picking_process_log.uidx.id` (`id`),
                INDEX `pickware_wms_picking_process_log.idx.picking_process_id` (`picking_process_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
