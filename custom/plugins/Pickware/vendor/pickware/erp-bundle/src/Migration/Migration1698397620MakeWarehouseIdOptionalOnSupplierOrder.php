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

class Migration1698397620MakeWarehouseIdOptionalOnSupplierOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1698397620;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_supplier_order`
                MODIFY COLUMN `warehouse_id` BINARY(16) DEFAULT NULL
                ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_supplier_order`
                ADD COLUMN `warehouse_snapshot` JSON DEFAULT NULL AFTER `warehouse_id`
                ');

        // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
        // Is already re-created in this migration and we do not touch it retrospectively.
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_supplier_order`
                DROP FOREIGN KEY `pickware_erp_supplier_order.fk.warehouse`
                ');
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_supplier_order`
                ADD CONSTRAINT `pickware_erp_supplier_order.fk.warehouse` FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse`(id) ON DELETE SET NULL');

        // Generate all snapshots that can be generated
        $connection->executeStatement('
            UPDATE `pickware_erp_supplier_order`
                SET `warehouse_snapshot` = (
                    SELECT JSON_OBJECT(
                        "name", `pickware_erp_warehouse`.`name`,
                        "code", `pickware_erp_warehouse`.`code`
                    )
                    FROM `pickware_erp_warehouse`
                    WHERE `pickware_erp_warehouse`.`id` = `pickware_erp_supplier_order`.`warehouse_id`
                )
                WHERE `pickware_erp_supplier_order`.`warehouse_id` IS NOT NULL
                ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_supplier_order`
                MODIFY COLUMN `warehouse_snapshot` JSON NOT NULL,
                ADD CHECK (json_valid(`warehouse_snapshot`));
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
