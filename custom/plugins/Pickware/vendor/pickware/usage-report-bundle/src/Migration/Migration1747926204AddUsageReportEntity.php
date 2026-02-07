<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1747926204AddUsageReportEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1747926204;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE IF NOT EXISTS `pickware_usage_report_usage_report` (
                    `id` BINARY(16) NOT NULL,
                    # Re-add hyphens tho the UUID V4 stored in the `id` column
                    `uuid` VARCHAR(36) GENERATED ALWAYS AS (
                        LOWER(CONCAT(
                            LEFT(HEX(id), 8), '-',
                            SUBSTRING(HEX(id), 9, 4), '-',
                            SUBSTRING(HEX(id), 13, 4), '-',
                            SUBSTRING(HEX(id), 17, 4), '-',
                            RIGHT(HEX(id), 12)
                        ))
                    ) VIRTUAL,
                    `order_count` INT NOT NULL,
                    `reported_at` DATETIME(3) NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `pickware_usage_report_order` (
                    `id` BINARY(16) NOT NULL,
                    `ordered_at` DATETIME(3) NOT NULL,
                    `order_snapshot` JSON NULL,
                    `order_id` BINARY(16) NULL,
                    `order_version_id` BINARY(16) NULL,
                    `is_pos_order` TINYINT(1) NOT NULL,
                    `usage_report_id` BINARY(16) NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE INDEX `pickware_usage_report_order.uidx.order` (`order_id`, `order_version_id`),
                    CONSTRAINT `pickware_usage_report_order.fk.order_id`
                        FOREIGN KEY (`order_id`, `order_version_id`)
                        REFERENCES `order` (`id`, `version_id`)
                            ON DELETE SET NULL
                            ON UPDATE CASCADE,
                    CONSTRAINT `pickware_usage_report_order.fk.usage_report_id`
                        FOREIGN KEY (`usage_report_id`)
                        REFERENCES `pickware_usage_report_usage_report` (`id`)
                            ON DELETE RESTRICT
                            ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
