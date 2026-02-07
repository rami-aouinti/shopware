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

class Migration1765359543BackfillUsageIntervalColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765359543;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_usage_report_order` usageReportOrder
                LEFT JOIN `order` ON `order`.`id` = usageReportOrder.`order_id`
                    AND `order`.`version_id` = usageReportOrder.`order_version_id`
                SET
                    usageReportOrder.`order_created_at` = COALESCE(`order`.`created_at`, usageReportOrder.`ordered_at`),
                    usageReportOrder.`order_created_at_hour` = DATE_FORMAT(COALESCE(`order`.`created_at`, usageReportOrder.`ordered_at`), "%Y-%m-%d %H:00:00.000")
                WHERE usageReportOrder.`order_created_at_hour` IS NULL;
                SQL,
        );

        // Strategy for backfilling the usage interval columns:
        // The usage interval end limit becomes the creation time of the usage report and the start limit becomes the
        // creation time of the previous usage report.
        // For the very first usage report, the start limit is the creation time of the usage report minus 1 hour.
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_usage_report_usage_report` usageReport
                JOIN (
                    SELECT
                        id,
                        created_at AS interval_end,
                        LAG(created_at) OVER (ORDER BY created_at) AS interval_start
                    FROM `pickware_usage_report_usage_report`
                    WHERE `usage_interval_inclusive_start` IS NULL
                ) intervalCalculation ON usageReport.id = intervalCalculation.id
                SET
                    usageReport.usage_interval_inclusive_start = COALESCE(intervalCalculation.interval_start, intervalCalculation.interval_end - INTERVAL 1 HOUR),
                    usageReport.usage_interval_exclusive_end = intervalCalculation.interval_end;
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_usage_report_usage_report`
                MODIFY COLUMN `usage_interval_inclusive_start` DATETIME(3) NOT NULL,
                MODIFY COLUMN `usage_interval_exclusive_end` DATETIME(3) NOT NULL;
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_usage_report_order`
                MODIFY COLUMN `order_created_at` DATETIME(3) NOT NULL,
                MODIFY COLUMN `order_created_at_hour` DATETIME(3) NOT NULL;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
