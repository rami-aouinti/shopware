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

class Migration1751210924AddPickwareWmsOrderSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751210924;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE IF NOT EXISTS `pickware_wms_order` (
                    `id` BINARY(16) NOT NULL,
                    `order_id` BINARY(16) NOT NULL,
                    `order_version_id` BINARY(16) NOT NULL,
                    `is_single_item_order` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `pickware_wms_order.fk.order`
                        FOREIGN KEY (`order_id`, `order_version_id`)
                        REFERENCES `order` (`id`, `version_id`)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
