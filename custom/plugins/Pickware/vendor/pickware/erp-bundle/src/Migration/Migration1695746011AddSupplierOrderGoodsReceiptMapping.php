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

class Migration1695746011AddSupplierOrderGoodsReceiptMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1695746011;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_erp_supplier_order_goods_receipt_mapping` (
                `supplier_order_id` BINARY(16) NOT NULL,
                `goods_receipt_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`supplier_order_id`, `goods_receipt_id`),
                CONSTRAINT `pckwr_erp_supplier_order_goods_receipt_mapping.fk.supplier_order`
                    FOREIGN KEY (`supplier_order_id`)
                    REFERENCES `pickware_erp_supplier_order` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pckwr_erp_supplier_order_goods_receipt_mapping.fk.goods_receipt`
                    FOREIGN KEY (`goods_receipt_id`)
                    REFERENCES `pickware_erp_goods_receipt` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $connection->executeStatement('
            INSERT INTO
                `pickware_erp_supplier_order_goods_receipt_mapping`
            SELECT
                `supplier_order_id`,
                `goods_receipt_id`
            FROM `pickware_erp_goods_receipt_line_item`
            WHERE `supplier_order_id` IS NOT NULL
            GROUP BY `supplier_order_id`, `goods_receipt_id`
        ');
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_goods_receipt_line_item`
                ADD CONSTRAINT `pickware_erp_goods_receipt_item.fk.supplier_order_goods_receipt`
                FOREIGN KEY (`supplier_order_id`, `goods_receipt_id`)
                REFERENCES `pickware_erp_supplier_order_goods_receipt_mapping` (`supplier_order_id`, `goods_receipt_id`)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
