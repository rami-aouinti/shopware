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

class Migration1556118797CreateStockSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556118797;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_special_stock_location` (
                `technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_location_type` (
                `technical_name` VARCHAR(255) NOT NULL,
                `internal` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_stock_movement` (
                `id` BINARY(16) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `comment` LONGTEXT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `source_description` VARCHAR(255) NOT NULL,
                `source_location_type_technical_name` VARCHAR(255) NOT NULL,
                `source_warehouse_id` BINARY(16) NULL,
                `source_bin_location_id` BINARY(16) NULL,
                `source_special_stock_location_technical_name` VARCHAR(255) NULL,
                `destination_description` VARCHAR(255) NOT NULL,
                `destination_location_type_technical_name` VARCHAR(255) NOT NULL,
                `destination_warehouse_id` BINARY(16) NULL,
                `destination_bin_location_id` BINARY(16) NULL,
                `destination_special_stock_location_technical_name` VARCHAR(255) NULL,
                `user_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `pickware_erp_stock_movement.idx.product` (`product_id`,`product_version_id`),
                INDEX `pickware_erp_stock_movement.idx.source_location_type` (`source_location_type_technical_name`),
                INDEX `pickware_erp_stock_movement.idx.source_warehouse` (`source_warehouse_id`),
                INDEX `pickware_erp_stock_movement.idx.source_bin_location` (`source_bin_location_id`),
                INDEX `pickware_erp_stock_movement.idx.source_special_stock_location` (`source_special_stock_location_technical_name`),
                INDEX `pickware_erp_stock_movement.idx.dest_location_type` (`destination_location_type_technical_name`),
                INDEX `pickware_erp_stock_movement.idx.dest_warehouse` (`destination_warehouse_id`),
                INDEX `pickware_erp_stock_movement.idx.dest_bin_location` (`destination_bin_location_id`),
                INDEX `pickware_erp_stock_movement.idx.dest_special_stock_location` (`destination_special_stock_location_technical_name`),
                INDEX `pickware_erp_stock_movement.idx.user` (`user_id`),
                CONSTRAINT `pickware_erp_stock_movement.fk.product`
                    FOREIGN KEY (`product_id`,`product_version_id`)
                    REFERENCES `product` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.source_location_type`
                    FOREIGN KEY (`source_location_type_technical_name`)
                    REFERENCES `pickware_erp_location_type` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.source_warehouse`
                    FOREIGN KEY (`source_warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.source_bin_location`
                    FOREIGN KEY (`source_bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.source_special_stock_location`
                    FOREIGN KEY (`source_special_stock_location_technical_name`)
                    REFERENCES `pickware_erp_special_stock_location` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.dest_location_type`
                    FOREIGN KEY (`destination_location_type_technical_name`)
                    REFERENCES `pickware_erp_location_type` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.dest_warehouse`
                    FOREIGN KEY (`destination_warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.dest_bin_location`
                    FOREIGN KEY (`destination_bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.dest_special_stock_location`
                    FOREIGN KEY (`destination_special_stock_location_technical_name`)
                    REFERENCES `pickware_erp_special_stock_location` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_movement.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_stock` (
                `id` BINARY(16) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `location_type_technical_name` VARCHAR(255) NOT NULL,
                `warehouse_id` BINARY(16) NULL,
                `bin_location_id` BINARY(16) NULL,
                `special_stock_location_technical_name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_stock.uidx.product.warehouse` (`product_id`,`product_version_id`, `warehouse_id`),
                UNIQUE INDEX `pickware_erp_stock.uidx.product.bin_location` (`product_id`,`product_version_id`, `bin_location_id`),
                UNIQUE INDEX `pickware_erp_stock.uidx.product.special_stock_location` (`product_id`,`product_version_id`, `special_stock_location_technical_name`),
                INDEX `pickware_erp_stock.idx.product` (`product_id`,`product_version_id`),
                INDEX `pickware_erp_stock.idx.location_type` (`location_type_technical_name`),
                INDEX `pickware_erp_stock.idx.warehouse` (`warehouse_id`),
                INDEX `pickware_erp_stock.idx.bin_location` (`bin_location_id`),
                INDEX `pickware_erp_stock.idx.special_stock_location` (`special_stock_location_technical_name`),
                CONSTRAINT `pickware_erp_stock.fk.product`
                    FOREIGN KEY (`product_id`,`product_version_id`)
                    REFERENCES `product` (`id`,`version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock.fk.location_type`
                    FOREIGN KEY (`location_type_technical_name`)
                    REFERENCES `pickware_erp_location_type` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock.fk.bin_location`
                    FOREIGN KEY (`bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock.fk.special_stock_location`
                    FOREIGN KEY (`special_stock_location_technical_name`)
                    REFERENCES `pickware_erp_special_stock_location` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
