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

class Migration1675861056AddProductSalesUpdateQueueSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1675861056;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_erp_product_sales_update_queue` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_product_sales_update_queue.uidx.product` (`product_id`, `product_version_id`),
                CONSTRAINT `pickware_erp_product_sales_update_queue.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
