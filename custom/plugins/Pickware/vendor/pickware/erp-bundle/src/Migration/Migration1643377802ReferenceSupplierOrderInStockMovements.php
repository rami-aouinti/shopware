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

class Migration1643377802ReferenceSupplierOrderInStockMovements extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1643377802;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock_movement`
            ADD COLUMN `source_supplier_order_id` BINARY(16) NULL
                AFTER `source_order_id`,
            ADD COLUMN `destination_supplier_order_id` BINARY(16) NULL
                AFTER `destination_order_id`,
            ADD CONSTRAINT `pickware_erp_stock_movement.fk.source_supplier_order`
                FOREIGN KEY (`source_supplier_order_id`)
                REFERENCES `pickware_erp_supplier_order` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            ADD CONSTRAINT `pickware_erp_stock_movement.fk.dest_supplier_order`
                FOREIGN KEY (`destination_supplier_order_id`)
                REFERENCES `pickware_erp_supplier_order` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
            ADD COLUMN `supplier_order_id` BINARY(16) NULL
                AFTER `order_version_id`,
            ADD UNIQUE INDEX `pickware_erp_stock.uidx.product.supplier_order` (
                `product_id`,
                `supplier_order_id`
            ),
            ADD CONSTRAINT `pickware_erp_stock.fk.supplier_order`
                FOREIGN KEY (`supplier_order_id`)
                REFERENCES `pickware_erp_supplier_order` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
