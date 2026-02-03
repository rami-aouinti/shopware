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

class Migration1614587720CreateDemandPlanningListItemSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614587720;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_demand_planning_session` (
                `id` BINARY(16) NOT NULL,
                `user_id` BINARY(16) NOT NULL,
                `configuration` JSON NOT NULL,
                `last_calculation` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_demand_planning_session.uidx.user` (`user_id`),
                CONSTRAINT `pickware_erp_demand_planning_session.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_demand_planning_list_item` (
                `id` BINARY(16) NOT NULL,
                `demand_planning_session_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `sales` INT(11) DEFAULT 0 NOT NULL,
                `sales_prediction` INT(11) DEFAULT 0 NOT NULL,
                `reserved_stock` INT(11) DEFAULT 0 NOT NULL,
                `purchase_suggestion` INT(11) DEFAULT 0 NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_demand_planning_list_item.uidx.config.product` (`demand_planning_session_id`, `product_id`, `product_version_id`),
                CONSTRAINT `pickware_erp_demand_planning_list_item.fk.config`
                    FOREIGN KEY (`demand_planning_session_id`)
                    REFERENCES `pickware_erp_demand_planning_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_demand_planning_list_item.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
