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

class Migration1743080939CreateOrderStockMovementProcessMappingSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1743080939;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_erp_order_stock_movement_process_mapping` (
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `stock_movement_process_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`order_id`, `stock_movement_process_id`),
                UNIQUE INDEX `pw_erp_stock_movement_process.uidx.stock_movement_process` (`stock_movement_process_id`),
                CONSTRAINT `pw_erp_stock_movement_process_mapping.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_erp_stock_movement_process_mapping.fk.stock_movement_process`
                    FOREIGN KEY (`stock_movement_process_id`)
                    REFERENCES `pickware_erp_stock_movement_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
