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

class Migration1663933707AllowSupplierDeletionInSupplierOrders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1663933707;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // Is already re-created in this migration and we do not touch it retrospectively.
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_supplier_order`
                MODIFY COLUMN `supplier_id` BINARY(16) NULL,
                ADD `supplier_snapshot` JSON NULL CHECK (json_valid(`supplier_snapshot`)) AFTER `supplier_id`,
                DROP FOREIGN KEY `pickware_erp_supplier_order.fk.supplier`;
        ');
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_supplier_order`
                ADD CONSTRAINT `pickware_erp_supplier_order.fk.supplier`
                    FOREIGN KEY (supplier_id) references pickware_erp_supplier (id)
                    ON UPDATE CASCADE ON DELETE SET NULL;
        ');

        // Generate all snapshots that can still be generated
        $connection->executeStatement(
            'UPDATE `pickware_erp_supplier_order` `supplier_order`
            INNER JOIN `pickware_erp_supplier` `supplier`
                ON `supplier_id` = `supplier`.`id`
            LEFT JOIN `pickware_erp_address` `address`
                ON `supplier`.`address_id` = `address`.`id`
            SET `supplier_snapshot` = JSON_OBJECT(
                "name",
                `supplier`.`name`,
                "number",
                `supplier`.`number`,
                "email",
                `address`.`email`,
                "phone",
                `address`.`phone`
            )',
        );

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_supplier_order`
                MODIFY COLUMN `supplier_snapshot` JSON NOT NULL,
                ADD CHECK (json_valid(`supplier_snapshot`));
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
