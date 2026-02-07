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
use function Pickware\InstallationLibrary\Migration\ensureCorrectCollationOfColumnForForeignKeyConstraint;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1705479966AddStockingProcessSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1705479966;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_stocking_process` (
                `id` BINARY(16) NOT NULL,
                `number` VARCHAR(255) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) NOT NULL,
                `stock_container_id` BINARY(16) NOT NULL,
                `user_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_wms_stocking_process.uidx.number` (`number`),
                UNIQUE INDEX `pickware_wms_stocking_process.uidx.stock_container` (`stock_container_id`),
                CONSTRAINT `pickware_wms_stocking_process.fk.state`
                    FOREIGN KEY (`state_id`)
                    REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_stocking_process.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_stocking_process.fk.stock_container`
                    FOREIGN KEY (`stock_container_id`)
                    REFERENCES `pickware_erp_stock_container` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_stocking_process.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $connection,
            'pickware_erp_location_type',
            'technical_name',
        );
        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $connection,
            'pickware_erp_special_stock_location',
            'technical_name',
        );
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_stocking_process_source` (
                `id` BINARY(16) NOT NULL,
                `stocking_process_id` BINARY(16) NOT NULL,
                `location_type_technical_name` VARCHAR(255) NOT NULL,
                `warehouse_id` BINARY(16) NULL,
                `bin_location_id` BINARY(16) NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `return_order_id` BINARY(16) NULL,
                `return_order_version_id` BINARY(16) NULL,
                `stock_container_id` BINARY(16) NULL,
                `special_stock_location_technical_name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pw_wms_stocking_process_source.fk.stocking_process`
                    FOREIGN KEY (`stocking_process_id`)
                    REFERENCES `pickware_wms_stocking_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_source.fk.location_type`
                    FOREIGN KEY (`location_type_technical_name`)
                    REFERENCES `pickware_erp_location_type` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_source.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_source.fk.bin_location`
                    FOREIGN KEY (`bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_source.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_source.fk.return_order`
                    FOREIGN KEY (`return_order_id`, `return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_source.fk.stock_container`
                    FOREIGN KEY (`stock_container_id`)
                    REFERENCES `pickware_erp_stock_container` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_source.fk.special_stock_location`
                    FOREIGN KEY (`special_stock_location_technical_name`)
                    REFERENCES `pickware_erp_special_stock_location` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $connection,
            'pickware_erp_location_type',
            'technical_name',
        );
        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $connection,
            'pickware_erp_special_stock_location',
            'technical_name',
        );
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_stocking_process_line_item` (
                `id` BINARY(16) NOT NULL,
                `stocking_process_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `position` INT(11) NOT NULL,
                `location_type_technical_name` VARCHAR(255) NOT NULL,
                `warehouse_id` BINARY(16) NULL,
                `bin_location_id` BINARY(16) NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `return_order_id` BINARY(16) NULL,
                `return_order_version_id` BINARY(16) NULL,
                `stock_container_id` BINARY(16) NULL,
                `special_stock_location_technical_name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pw_wms_stocking_process_line_item.uidx.position` (`position`, `stocking_process_id`),
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.stocking_process`
                    FOREIGN KEY (`stocking_process_id`)
                    REFERENCES `pickware_wms_stocking_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.location_type`
                    FOREIGN KEY (`location_type_technical_name`)
                    REFERENCES `pickware_erp_location_type` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.bin_location`
                    FOREIGN KEY (`bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.return_order`
                    FOREIGN KEY (`return_order_id`, `return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.stock_container`
                    FOREIGN KEY (`stock_container_id`)
                    REFERENCES `pickware_erp_stock_container` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_stocking_process_line_item.fk.special_stock_location`
                    FOREIGN KEY (`special_stock_location_technical_name`)
                    REFERENCES `pickware_erp_special_stock_location` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
