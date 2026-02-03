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

class Migration1709111603AddDeviceSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1709111603;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_device` (
                `id` BINARY(16) NOT NULL,
                `device_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_stocking_process`
            ADD COLUMN `device_id` BINARY(16) NULL AFTER `user_id`,
            ADD CONSTRAINT `pickware_wms_stocking_process.fk.device`
                FOREIGN KEY (`device_id`)
                REFERENCES `pickware_wms_device` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
