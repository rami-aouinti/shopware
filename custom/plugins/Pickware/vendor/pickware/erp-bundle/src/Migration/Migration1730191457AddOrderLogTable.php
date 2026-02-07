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

class Migration1730191457AddOrderLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730191457;
    }

    public function update(Connection $connection): void
    {
        // No foreign key constraint so the order can be deleted.
        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_order_log` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_shipment_created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `pickware_erp_order_log.idx.order_id` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
