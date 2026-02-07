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

class Migration1699382618AddGoodsReceiptsForReturnOrders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699382618;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_erp_return_order_goods_receipt_mapping` (
                `return_order_id` BINARY(16) NOT NULL,
                `pickware_erp_return_order_version_id` BINARY(16) NOT NULL,
                `goods_receipt_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`return_order_id`, `pickware_erp_return_order_version_id`, `goods_receipt_id`),
                CONSTRAINT `pckwr_erp_return_order_goods_receipt_mapping.fk.return_order`
                    FOREIGN KEY (`return_order_id`, `pickware_erp_return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pckwr_erp_return_order_goods_receipt_mapping.fk.goods_receipt`
                    FOREIGN KEY (`goods_receipt_id`)
                    REFERENCES `pickware_erp_goods_receipt` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_goods_receipt`
            ADD COLUMN `customer_id` BINARY(16) NULL DEFAULT NULL AFTER `supplier_snapshot`,
            ADD COLUMN `customer_snapshot` JSON NULL DEFAULT NULL AFTER `customer_id`,
            ADD COLUMN `type` VARCHAR(255) NOT NULL AFTER `number`,
            ADD CONSTRAINT `pickware_erp_goods_receipt.fk.customer`
                FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE;
        ');

        $connection->executeStatement('
            UPDATE `pickware_erp_goods_receipt`
            SET `type` = IF(`pickware_erp_goods_receipt`.`supplier_id` IS NOT NULL, "supplier", "free")
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_goods_receipt_line_item`
            ADD COLUMN `return_order_id` BINARY(16) NULL DEFAULT NULL AFTER `supplier_order_id`,
            ADD COLUMN `return_order_version_id` BINARY(16) NULL DEFAULT NULL AFTER `return_order_id`,
            ADD CONSTRAINT `pickware_erp_goods_receipt.fk.return_order`
                FOREIGN KEY (`return_order_id`, `return_order_version_id`)
                REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            ADD CONSTRAINT `pckwr_erp_goods_receipt_line_item.fk.return_order_goods_receipt`
                FOREIGN KEY (`return_order_id`, `return_order_version_id`, `goods_receipt_id`)
                REFERENCES `pickware_erp_return_order_goods_receipt_mapping` (`return_order_id`, `pickware_erp_return_order_version_id`, `goods_receipt_id`)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
