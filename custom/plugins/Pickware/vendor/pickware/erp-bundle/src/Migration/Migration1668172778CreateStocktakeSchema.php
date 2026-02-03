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

class Migration1668172778CreateStocktakeSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1668172778;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_erp_stocktaking_stocktake` (
                `id` BINARY(16) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `is_active` BOOL GENERATED ALWAYS AS (IF(ISNULL(`import_export_id`), 1, 0)) VIRTUAL,
                `number` VARCHAR(255) NOT NULL,
                `completed_at` DATETIME NULL DEFAULT NULL,
                `warehouse_id` BINARY(16) NULL DEFAULT NULL,
                `warehouse_snapshot` JSON NOT NULL,
                `import_export_id` BINARY(16) NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_stocktaking_stocktake.uidx.number` (`number`),
                CONSTRAINT `pickware_erp_stocktaking_stocktake.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stocktaking_stocktake.fk.import_export`
                    FOREIGN KEY (`import_export_id`)
                    REFERENCES `pickware_erp_import_export` (`id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `pickware_erp_stocktaking_stocktake_counting_process` (
                `id` BINARY(16) NOT NULL,
                `number` VARCHAR(255) NOT NULL,
                `stocktake_id` BINARY(16) NOT NULL,
                `bin_location_id` BINARY(16) NULL DEFAULT NULL,
                `bin_location_snapshot` JSON NULL DEFAULT NULL,
                `user_id` BINARY(16) NULL DEFAULT NULL,
                `user_snapshot` JSON NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_stocktaking_stocktake_counting_process.uidx.number` (`number`),
                CONSTRAINT `pickware_erp_stocktaking_stocktake_counting_process.fk.stocktake`
                    FOREIGN KEY (`stocktake_id`)
                    REFERENCES `pickware_erp_stocktaking_stocktake` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stocktaking_s_counting_process.fk.bin_location`
                    FOREIGN KEY (`bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stocktaking_stocktake_counting_process.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_stocktake_counting_process.uidx.bin_location_stock_take`
                    UNIQUE (`stocktake_id`, `bin_location_id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `pickware_erp_stocktaking_stocktake_counting_process_item` (
                `id` BINARY(16) NOT NULL,
                `counting_process_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NULL,
                `product_snapshot` JSON NOT NULL,
                `quantity` INT(11) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_stocktake_counting_process_item.fk.counting_process`
                    FOREIGN KEY (`counting_process_id`)
                    REFERENCES `pickware_erp_stocktaking_stocktake_counting_process` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stocktaking_s_counting_process_item.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_stocktake_counting_process.uidx.counting_process_produc`
                    UNIQUE (`counting_process_id`, `product_id`, `product_version_id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
