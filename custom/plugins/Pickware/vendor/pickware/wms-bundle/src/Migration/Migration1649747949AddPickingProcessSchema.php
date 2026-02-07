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

class Migration1649747949AddPickingProcessSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649747949;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_wms_picking_process` (
                `id` BINARY(16) NOT NULL,
                `user_id` BINARY(16) NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_wms_picking_process.uidx.order` (`order_id`, `order_version_id`),
                CONSTRAINT `pickware_wms_picking_process.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_picking_process.fk.state`
                    FOREIGN KEY (`state_id`)
                    REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_picking_process.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_picking_process.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_wms_picking_process_tracking_code` (
                `id` BINARY(16) NOT NULL,
                `picking_process_id` BINARY(16) NOT NULL,
                `code` VARCHAR(255) NOT NULL,
                `shipped` TINYINT(1) NOT NULL,
                `tracking_url` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pw_wms_picking_process_tracking_code.uidx.code` (`code`),
                CONSTRAINT `pw_wms_picking_process_tracking_code.fk.picking_process`
                    FOREIGN KEY (`picking_process_id`)
                    REFERENCES `pickware_wms_picking_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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
            'CREATE TABLE `pickware_wms_picking_process_reserved_item` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `picking_process_id` BINARY(16) NOT NULL,
                `location_snapshot` JSON NULL,
                `location_type_technical_name` VARCHAR(255) NOT NULL,
                `warehouse_id` BINARY(16) NULL,
                `bin_location_id` BINARY(16) NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `return_order_id` BINARY(16) NULL,
                `return_order_version_id` BINARY(16) NULL,
                `supplier_order_id` BINARY(16) NULL,
                `special_stock_location_technical_name` VARCHAR(255) NULL,
                `quantity` INT(11) NOT NULL,
                `position` INT(11) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pw_wms_picking_process_reserved_item.uidx.position` (`position`, `picking_process_id`),
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.picking_process`
                    FOREIGN KEY (`picking_process_id`)
                    REFERENCES `pickware_wms_picking_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.location_type`
                    FOREIGN KEY (`location_type_technical_name`)
                    REFERENCES `pickware_erp_location_type` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.bin_location`
                    FOREIGN KEY (`bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.supplier_order`
                    FOREIGN KEY (`supplier_order_id`)
                    REFERENCES `pickware_erp_supplier_order` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.return_order`
                    FOREIGN KEY (`return_order_id`, `return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_reserved_item.fk.special_stock_location`
                    FOREIGN KEY (`special_stock_location_technical_name`)
                    REFERENCES `pickware_erp_special_stock_location` (`technical_name`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_wms_picking_process_order_document_mapping` (
                `picking_process_id` BINARY(16) NOT NULL,
                `order_document_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`picking_process_id`, `order_document_id`),
                CONSTRAINT `pw_wms_picking_process_order_document_map.fk.process`
                    FOREIGN KEY (`picking_process_id`)
                    REFERENCES `pickware_wms_picking_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_order_document_map.fk.document`
                    FOREIGN KEY (`order_document_id`)
                    REFERENCES `document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_wms_picking_process_document_mapping` (
                `picking_process_id` BINARY(16) NOT NULL,
                `document_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`picking_process_id`, `document_id`),
                CONSTRAINT `pw_wms_picking_process_document_map.fk.process`
                    FOREIGN KEY (`picking_process_id`)
                    REFERENCES `pickware_wms_picking_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_wms_picking_process_document_map.fk.document`
                    FOREIGN KEY (`document_id`)
                    REFERENCES `pickware_document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
