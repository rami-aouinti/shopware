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

class Migration1649759025AddStockContainerSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649759025;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_stock_container` (
                `id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock_movement`
            ADD COLUMN `source_stock_container_id` BINARY(16) NULL
                AFTER `source_order_version_id`,
            ADD COLUMN `destination_stock_container_id` BINARY(16) NULL
                AFTER `destination_order_version_id`,
            ADD INDEX `pickware_erp_stock_movement.idx.source_container` (`source_stock_container_id`),
            ADD CONSTRAINT `pickware_erp_stock_movement.fk.source_container`
                    FOREIGN KEY (`source_stock_container_id`)
                    REFERENCES `pickware_erp_stock_container` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
            ADD INDEX `pickware_erp_stock_movement.idx.dest_container` (`destination_order_version_id`),
            ADD CONSTRAINT `pickware_erp_stock_movement.fk.dest_container`
                    FOREIGN KEY (`destination_stock_container_id`)
                    REFERENCES `pickware_erp_stock_container` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
            ADD COLUMN `stock_container_id` BINARY(16) NULL
                AFTER `supplier_order_id`,
            ADD UNIQUE INDEX `pickware_erp_stock.uidx.product.stock_container` (`product_id`, `stock_container_id`),
            ADD CONSTRAINT `pickware_erp_stock.fk.stock_container`
                FOREIGN KEY (`stock_container_id`)
                REFERENCES `pickware_erp_stock_container` (`id`)
                ON DELETE RESTRICT
                ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
