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

class Migration1679922535AddGoodsReceiptSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1679922535;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_goods_receipt` (
                `id` BINARY(16) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `number` VARCHAR(255) NOT NULL,
                `comment` LONGTEXT NULL,
                `user_id` BINARY(16) NULL,
                `user_snapshot` JSON NOT NULL,
                `warehouse_id` BINARY(16) NULL,
                `warehouse_snapshot` JSON NOT NULL,
                `updated_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_goods_receipt.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_goods_receipt.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_goods_receipt.fk.state`
                    FOREIGN KEY (`state_id`)
                    REFERENCES `state_machine_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_goods_receipt_item` (
                `id` BINARY(16) NOT NULL,
                `goods_receipt_id` BINARY(16) NOT NULL,
                `quantity` INT(11),
                `product_id` BINARY(16) NULL,
                `product_version_id` binary(16)  null,
                `product_snapshot` JSON NOT NULL,
                `price` JSON NULL,
                `price_definition` JSON NULL,
                `unit_price` DOUBLE GENERATED ALWAYS AS (IF(`price` IS NULL, NULL, JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.unitPrice")))) VIRTUAL,
                `total_price` DOUBLE GENERATED ALWAYS AS (IF(`price` IS NULL, NULL, JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.totalPrice")))) VIRTUAL,
                `supplier_order_id` BINARY(16) NULL DEFAULT NULL,
                `updated_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_goods_receipt_item.fk.goods_receipt`
                    FOREIGN KEY (`goods_receipt_id`)
                    REFERENCES `pickware_erp_goods_receipt` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_goods_receipt_item.fk.supplier_order`
                    FOREIGN KEY (`supplier_order_id`)
                    REFERENCES `pickware_erp_supplier_order` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock_movement`
            ADD COLUMN `source_goods_receipt_id` BINARY(16) NULL
                AFTER `source_stock_container_id`,
            ADD COLUMN `destination_goods_receipt_id` BINARY(16) NULL
                AFTER `destination_stock_container_id`,
            ADD INDEX `pickware_erp_stock_movement.idx.source_goods_receipt` (`source_goods_receipt_id`),
            ADD CONSTRAINT `pickware_erp_stock_movement.fk.source_goods_receipt`
                    FOREIGN KEY (`source_goods_receipt_id`)
                    REFERENCES `pickware_erp_goods_receipt` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
            ADD INDEX `pickware_erp_stock_movement.idx.dest_goods_receipt` (`destination_goods_receipt_id`),
            ADD CONSTRAINT `pickware_erp_stock_movement.fk.dest_goods_receipt`
                    FOREIGN KEY (`destination_goods_receipt_id`)
                    REFERENCES `pickware_erp_goods_receipt` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
            ADD COLUMN `goods_receipt_id` BINARY(16) NULL
                AFTER `stock_container_id`,
            ADD UNIQUE INDEX `pickware_erp_stock.uidx.product.goods_receipt` (`product_id`, `goods_receipt_id`),
            ADD CONSTRAINT `pickware_erp_stock.fk.goods_receipt`
                FOREIGN KEY (`goods_receipt_id`)
                REFERENCES `pickware_erp_goods_receipt` (`id`)
                ON DELETE RESTRICT
                ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
