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

class Migration1556014424CreateWarehouseSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556014424;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_warehouse` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `code` VARCHAR(255) NOT NULL,
                `address_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_warehouse.uidx.name` (`name`),
                UNIQUE INDEX `pickware_erp_warehouse.uidx.code` (`code`),
                UNIQUE INDEX `pickware_erp_warehouse.uidx.address` (`address_id`),
                CONSTRAINT `pickware_erp_warehouse.fk.address`
                    FOREIGN KEY (`address_id`)
                    REFERENCES `pickware_erp_address` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_bin_location` (
                `id` BINARY(16) NOT NULL,
                `code` VARCHAR(255) NOT NULL,
                `warehouse_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_bin_location.uidx.code` (`warehouse_id`, `code`),
                INDEX `pickware_erp_bin_location.idx.warehouse` (`warehouse_id`),
                CONSTRAINT `pickware_erp_bin_location.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
