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

class Migration1648127352MigrateDemandPlanningToAnalytics extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648127352;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'INSERT INTO `pickware_erp_analytics_profile`
                (`technical_name`)
                VALUES ("demand_planning")
                ON DUPLICATE KEY UPDATE `technical_name` = `technical_name`',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_analytics_list_item_demand_planning` (
                `id` BINARY(16) NOT NULL,
                `analytics_session_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `sales` INT(11) DEFAULT 0 NOT NULL,
                `sales_prediction` INT(11) DEFAULT 0 NOT NULL,
                `reserved_stock` INT(11) DEFAULT 0 NOT NULL,
                `stock` INT(11) DEFAULT 0 NOT NULL,
                `reorder_point` INT(11) NULL,
                `incoming_stock` INT(11) DEFAULT 0 NOT NULL,
                `purchase_suggestion` INT(11) DEFAULT 0 NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_analytics_li_demand_planning.uidx.config.product` (`analytics_session_id`, `product_id`, `product_version_id`),
                CONSTRAINT `pickware_erp_analytics_li_demand_planning.fk.session`
                    FOREIGN KEY (`analytics_session_id`)
                    REFERENCES `pickware_erp_analytics_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_analytics_li_demand_planning.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'INSERT INTO `pickware_erp_analytics_session` (
                `id`,
                `profile_technical_name`,
                `user_id`,
                `last_calculation`,
                `configuration`,
                `created_at`,
                `updated_at`
            ) SELECT
                `id`,
                "demand_planning",
                `user_id`,
                `last_calculation`,
                `configuration`,
                `created_at`,
                `updated_at`
            FROM `pickware_erp_demand_planning_session`;',
        );

        $connection->executeStatement(
            'INSERT INTO `pickware_erp_analytics_list_item_demand_planning` (
                `id`,
                `analytics_session_id`,
                `product_id`,
                `product_version_id`,
                `sales`,
                `sales_prediction`,
                `reserved_stock`,
                `stock`,
                `reorder_point`,
                `incoming_stock`,
                `purchase_suggestion`,
                `created_at`,
                `updated_at`
            ) SELECT
                `id`,
                `demand_planning_session_id`,
                `product_id`,
                `product_version_id`,
                `sales`,
                `sales_prediction`,
                `reserved_stock`,
                `stock`,
                `reorder_point`,
                `incoming_stock`,
                `purchase_suggestion`,
                `created_at`,
                `updated_at`
            FROM `pickware_erp_demand_planning_list_item`;',
        );

        $connection->executeStatement('
            DROP TABLE `pickware_erp_demand_planning_list_item`;
            DROP TABLE `pickware_erp_demand_planning_session`;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
