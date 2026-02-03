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

class Migration1649752132AddDemandPlanningAnalyticsAggregationSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649752132;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `pickware_erp_analytics_list_item_demand_planning`;');

        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_analytics_aggregation_item_demand_planning` (
                `id` BINARY(16) NOT NULL,
                `aggregation_session_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `sales` INT(11) DEFAULT 0 NOT NULL,
                `sales_prediction` INT(11) DEFAULT 0 NOT NULL,
                `reserved_stock` INT(11) DEFAULT 0 NOT NULL,
                `stock` INT(11) DEFAULT 0 NOT NULL,
                `reorder_point` INT(11) NULL,
                `incoming_stock` INT(11) DEFAULT 0 NOT NULL,
                `purchase_suggestion` INT(11) DEFAULT 0 NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_analytics_ai_demand_planning.uidx.config.product` (`aggregation_session_id`, `product_id`, `product_version_id`),
                CONSTRAINT `pickware_erp_analytics_ai_demand_planning.fk.aggregation_session`
                    FOREIGN KEY (`aggregation_session_id`)
                    REFERENCES `pickware_erp_analytics_aggregation_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_analytics_ai_demand_planning.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            );',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_demand_planning_list_item` (
                `id` BINARY(16) NOT NULL,
                `report_config_id` BINARY(16) NOT NULL,
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
                UNIQUE INDEX `pickware_erp_demand_planning_list_item.uidx.config.product` (`report_config_id`, `product_id`, `product_version_id`),
                CONSTRAINT `pickware_erp_demand_planning_list_item.fk.report_config`
                    FOREIGN KEY (`report_config_id`)
                    REFERENCES `pickware_erp_analytics_report_config` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_demand_planning_list_item.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
