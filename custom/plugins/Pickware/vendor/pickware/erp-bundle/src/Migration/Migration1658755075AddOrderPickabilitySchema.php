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

class Migration1658755075AddOrderPickabilitySchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1658755075;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_order_pickability` (
                `id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `order_pickability_status` ENUM("completely_pickable", "partially_pickable", "not_pickable") NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                UNIQUE INDEX `pickware_erp_order_pickability.uidx.warehouse.order` (`warehouse_id`, `order_id`, `order_version_id`),
                CONSTRAINT `pickware_erp_order_pickability.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_order_pickability.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            );',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
