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

class Migration1668172779CreateStocktakeProductSummarySchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1668172779;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_erp_stocktaking_stocktake_product_summary` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `stocktake_id` BINARY(16) NOT NULL,
                `counted_stock` INT(11) NOT NULL,
                `absolute_stock_difference` INT(11) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_stocktaking_sp_summary.uidx.stocktake_product` (`stocktake_id`, `product_id`, `product_version_id`),
                CONSTRAINT `pickware_erp_stocktaking_sp_summary.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stocktaking_sp_summary.fk.stocktake`
                    FOREIGN KEY (`stocktake_id`)
                    REFERENCES `pickware_erp_stocktaking_stocktake` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
