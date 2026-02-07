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

class Migration1621419761AddSupplierOrderSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1621419761;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_supplier_order` (
                `id` BINARY(16) NOT NULL,
                `supplier_id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) NOT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `payment_state_id` BINARY(16) NOT NULL,
                `number` VARCHAR(255) NOT NULL,
                `supplier_order_number` VARCHAR(255) NULL,
                `order_date_time` DATETIME(3) NOT NULL,
                `due_date` DATETIME(3) NULL,
                `delivery_date` DATETIME(3) NULL,
                `total_net` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `total_gross` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_supplier_order.uidx.number` (`number`),
                CONSTRAINT `pickware_erp_supplier_order.fk.supplier`
                    FOREIGN KEY (`supplier_id`)
                    REFERENCES `pickware_erp_supplier` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_supplier_order.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_supplier_order.fk.currency`
                    FOREIGN KEY (`currency_id`)
                    REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_supplier_order.fk.state`
                    FOREIGN KEY (`state_id`)
                    REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_supplier_order.fk.payment_state`
                    FOREIGN KEY (`payment_state_id`)
                    REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_supplier_order_line_item` (
                `id` BINARY(16) NOT NULL,
                `supplier_order_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) DEFAULT NULL,
                `product_version_id` BINARY(16) DEFAULT NULL,
                `product_snapshot` JSON NOT NULL,
                `quantity` INT(11) DEFAULT 0 NOT NULL,
                `price_net` DECIMAL(10,2) NOT NULL,
                `price_gross` DECIMAL(10,2) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_supplier_order_line_item.uidx.product.order` (`product_id`, `product_version_id`, `supplier_order_id`),
                CONSTRAINT `pickware_erp_supplier_order_line_item.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_supplier_order_line_item.fk.order`
                    FOREIGN KEY (`supplier_order_id`)
                    REFERENCES `pickware_erp_supplier_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
