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

class Migration1649759025AddWarehouseReferenceToStockContainerSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1657525411;
    }

    /**
     * We initially added this column to the previous Migration (Migration1649759025AddStockContainerSchema), that was
     * already released, by accident. Existing customers who updated to the last release and who did execute that
     * migration in its original state before never executed the change to that migration (that is why we NEVER change
     * a released migration). This migration correctly adds the column in a separate migration. But for customers wo
     * executed the updated Migration (Migration1649759025AddStockContainerSchema (updated)), we need to make an
     * existing check beforehand.
     */
    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM `pickware_erp_stock_container` LIKE "%warehouse_id%";');
        if (in_array('warehouse_id', array_column($columns, 'Field'))) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock_container`
            ADD COLUMN `warehouse_id` BINARY(16) NOT NULL AFTER `id`,
            ADD FOREIGN KEY `pickware_erp_stock_container.fk.warehouse` (`warehouse_id`)
            REFERENCES `pickware_erp_warehouse` (`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
