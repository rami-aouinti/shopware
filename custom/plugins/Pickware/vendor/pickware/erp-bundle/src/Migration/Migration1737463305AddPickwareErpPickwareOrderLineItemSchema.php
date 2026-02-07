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

class Migration1737463305AddPickwareErpPickwareOrderLineItemSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1737463305;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_pickware_order_line_item` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `order_line_item_id` BINARY(16) NOT NULL,
                `order_line_item_version_id` BINARY(16) NOT NULL,
                `externally_fulfilled_quantity` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                UNIQUE INDEX `pickware_erp_pickware_order_line_item.uidx.order_line_item` (`order_line_item_id`, `order_line_item_version_id`),
                CONSTRAINT `pickware_erp_pickware_order_line_item.fk.order_line_item`
                    FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`)
                    REFERENCES `order_line_item` (`id`, `version_id`)
                    ON DELETE CASCADE,
                PRIMARY KEY (`id`, `version_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
