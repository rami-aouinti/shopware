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

class Migration1741956402AddStockMovementProcessSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1741956402;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_stock_movement_process_type` (
                `technical_name` VARCHAR(255) NOT NULL,
                `referenced_entity_field_name` VARCHAR(255) NOT NULL,
                `referenced_entity_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3),
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_stock_movement_process` (
                `id` BINARY(16) NOT NULL,
                `type_technical_name` VARCHAR(255) NOT NULL,
                `referenced_entity_snapshot` JSON NOT NULL,
                `user_id` BINARY(16) DEFAULT NULL,
                `user_snapshot` JSON NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3),
                PRIMARY KEY (`id`),
                CONSTRAINT `pw_erp_stock_movement_process.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pw_erp_stock_movement_process.fk.type`
                    FOREIGN KEY (`type_technical_name`)
                    REFERENCES `pickware_erp_stock_movement_process_type` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stock_movement`
                ADD COLUMN `stock_movement_process_id` BINARY(16) NULL AFTER `product_version_id`,
                ADD FOREIGN KEY `pw_erp_stock_movement.fk.stock_movement_process` (`stock_movement_process_id`)
                    REFERENCES `pickware_erp_stock_movement_process` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
