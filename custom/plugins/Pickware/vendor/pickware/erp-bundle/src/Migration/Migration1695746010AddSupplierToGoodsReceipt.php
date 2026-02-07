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

class Migration1695746010AddSupplierToGoodsReceipt extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1695746010;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_goods_receipt`
            ADD COLUMN `supplier_id` BINARY(16) NULL DEFAULT NULL AFTER `warehouse_snapshot`,
            ADD COLUMN `supplier_snapshot` JSON NULL DEFAULT NULL AFTER `supplier_id`,
            ADD CONSTRAINT `pickware_erp_goods_receipt.fk.supplier`
                FOREIGN KEY (`supplier_id`)
                REFERENCES `pickware_erp_supplier` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ');

        $connection->executeQuery('
            UPDATE `pickware_erp_goods_receipt`
            INNER JOIN (
                SELECT
                    `pickware_erp_goods_receipt_line_item`.`goods_receipt_id` AS `goods_receipt_id`,
                    MAX(`pickware_erp_supplier`.`id`) as `supplier_id`,
                    MAX(`pickware_erp_supplier`.`name`) as `supplier_name`,
                    MAX(`pickware_erp_supplier`.`number`) as `supplier_number`
                FROM `pickware_erp_goods_receipt_line_item`
                INNER JOIN `pickware_erp_supplier_order` ON `pickware_erp_goods_receipt_line_item`.`supplier_order_id` = `pickware_erp_supplier_order`.`id`
                INNER JOIN `pickware_erp_supplier` ON `pickware_erp_supplier_order`.`supplier_id` = `pickware_erp_supplier`.`id`
                GROUP BY `pickware_erp_goods_receipt_line_item`.`goods_receipt_id`
            ) AS `mapping` ON `mapping`.`goods_receipt_id` = `pickware_erp_goods_receipt`.`id`
            SET
                `pickware_erp_goods_receipt`.`supplier_id` = `mapping`.`supplier_id`,
                `pickware_erp_goods_receipt`.`supplier_snapshot` = JSON_OBJECT(
                    "name", `mapping`.`supplier_name`,
                    "number", `mapping`.`supplier_number`
                )
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
