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

class Migration1649314350ChangeAnalyticsToUseAggregationsAndReports extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649314350;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // (false positive) Table does not exist anymore after this migration, so we do not need to drop the foreign key index retrospectively.
    public function update(Connection $connection): void
    {
        $this->addAggregationSchema($connection);
        $this->addReportSchema($connection);

        $connection->executeStatement('TRUNCATE `pickware_erp_analytics_list_item_demand_planning`;');
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_analytics_list_item_demand_planning`
            DROP FOREIGN KEY `pickware_erp_analytics_li_demand_planning.fk.session`;',
        );

        $connection->executeStatement('DROP TABLE `pickware_erp_analytics_session`;');
        $connection->executeStatement('DROP TABLE `pickware_erp_analytics_profile`;');
    }

    public function updateDestructive(Connection $connection): void {}

    private function addAggregationSchema(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_analytics_aggregation` (
                `technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_analytics_aggregation_session` (
                `id` BINARY(16) NOT NULL,
                `aggregation_technical_name` VARCHAR(255) NOT NULL,
                `config` JSON NOT NULL,
                `user_id` BINARY(16) NOT NULL,
                `last_calculation` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_analytics_aggregation_session.fk.aggregation`
                    FOREIGN KEY (`aggregation_technical_name`)
                    REFERENCES `pickware_erp_analytics_aggregation` (`technical_name`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_analytics_aggregation_session.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    private function addReportSchema(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_analytics_report` (
                `technical_name` VARCHAR(255) NOT NULL,
                `aggregation_technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`technical_name`),
                CONSTRAINT `pickware_erp_analytics_report.fk.aggregation`
                     FOREIGN KEY (`aggregation_technical_name`)
                     REFERENCES `pickware_erp_analytics_aggregation` (`technical_name`)
                         ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_analytics_report_config` (
                `id` BINARY(16) NOT NULL,
                `report_technical_name` VARCHAR(255) NOT NULL,
                `aggregation_session_id` BINARY(16) NOT NULL,
                `list_query` JSON NULL,
                `calculator_config` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_analytics_report_config.fk.report`
                    FOREIGN KEY (`report_technical_name`)
                    REFERENCES `pickware_erp_analytics_report` (`technical_name`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_analytics_report_config.fk.aggregation_session`
                    FOREIGN KEY (`aggregation_session_id`)
                    REFERENCES `pickware_erp_analytics_aggregation_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }
}
