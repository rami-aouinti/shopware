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
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1649759026AddStockContainerToPickingProcess extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649759026;
    }

    public function update(Connection $connection): void
    {
        // This migration was added later than the timestamp suggests. This was done because the previous migration
        // Migration1649747949AddPickingProcessSchema was edited even though other migrations were already based on it.
        // To fix the problem the named migration was split into two migrations.

        // Since this migration is new to system that update the bundle, we have to effectively avoid running this
        // migration in such a case.
        // In migration Migration1676300213AddDelivery the column stock_container_id is dropped again. When the table
        // `pickware_wm_delivery` exists, which is created in the same migration, we assume that those migration and
        // all previous ones did run successfully, and we can skip this migration.
        $deliveryTableExists = $connection->fetchOne(
            'SELECT 1
            FROM information_schema.TABLES
            WHERE
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "pickware_wms_delivery"',
        );

        if ($deliveryTableExists) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process`
            ADD COLUMN `stock_container_id` BINARY(16) NOT NULL AFTER `warehouse_id`,
            ADD UNIQUE INDEX `pickware_wms_picking_process.uidx.stock_container` (`stock_container_id`),
            ADD CONSTRAINT `pickware_wms_picking_process.fk.stock_container`
                FOREIGN KEY (`stock_container_id`)
                REFERENCES `pickware_erp_stock_container` (`id`)
                ON DELETE RESTRICT
                ON UPDATE CASCADE;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_reserved_item`
            ADD COLUMN `stock_container_id` BINARY(16) NULL AFTER `supplier_order_id`,
            ADD CONSTRAINT `pw_wms_picking_process_reserved_item.fk.stock_container`
                    FOREIGN KEY (`stock_container_id`)
                    REFERENCES `pickware_erp_stock_container` (`id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
