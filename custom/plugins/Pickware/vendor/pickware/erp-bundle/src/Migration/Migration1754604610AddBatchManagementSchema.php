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

class Migration1754604610AddBatchManagementSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1754604610;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_batch` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `internal_number` VARCHAR(255) NOT NULL,
                `external_number` VARCHAR(255) NULL,
                `production_date` DATE NULL,
                `best_before_date` DATE NULL,
                `comment` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_batch.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_batch.uidx.internal_number`
                    UNIQUE KEY (`internal_number`),
                CONSTRAINT `pickware_erp_batch.uidx.external_number`
                    UNIQUE KEY (`external_number`, `product_id`, `product_version_id`),
                UNIQUE `pickware_erp_batch.idx.id_product`
                    (`id`, `product_id`, `product_version_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stock`
                ADD UNIQUE `pickware_erp_stock.idx.id_product` (`id`, `product_id`, `product_version_id`)
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_batch_stock_mapping` (
                `id` BINARY(16) NOT NULL,
                `stock_id` BINARY(16) NOT NULL,
                `batch_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_batch_stock_mapping.fk.stock`
                    FOREIGN KEY (`stock_id`, `product_id`, `product_version_id`)
                    REFERENCES `pickware_erp_stock` (`id`, `product_id`, `product_version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_batch_stock_mapping.fk.batch`
                    FOREIGN KEY (`batch_id`, `product_id`, `product_version_id`)
                    REFERENCES `pickware_erp_batch` (`id`, `product_id`, `product_version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_batch_stock_mapping.uidx.stock_batch`
                    UNIQUE KEY (`batch_id`, `stock_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stock_movement`
                ADD UNIQUE `pickware_erp_stock_movement.idx.id_product` (`id`, `product_id`, `product_version_id`)
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_batch_stock_movement_mapping` (
                `id` BINARY(16) NOT NULL,
                `stock_movement_id` BINARY(16) NOT NULL,
                `batch_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `quantity` DECIMAL(10,2) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_batch_stock_movement_mapping.fk.stock_movement`
                    FOREIGN KEY (`stock_movement_id`, `product_id`, `product_version_id`)
                    REFERENCES `pickware_erp_stock_movement` (`id`, `product_id`, `product_version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_batch_stock_movement_mapping.fk.batch`
                    FOREIGN KEY (`batch_id`, `product_id`, `product_version_id`)
                    REFERENCES `pickware_erp_batch` (`id`, `product_id`, `product_version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_batch_stock_movement_mapping.uidx.batch_sm`
                    UNIQUE KEY (`batch_id`, `stock_movement_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
