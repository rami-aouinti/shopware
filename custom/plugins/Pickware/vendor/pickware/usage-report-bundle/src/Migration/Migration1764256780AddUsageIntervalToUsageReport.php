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

class Migration1764256780AddUsageIntervalToUsageReport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764256780;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_usage_report_usage_report`
                ADD COLUMN `usage_interval_inclusive_start` DATETIME(3) NULL AFTER `reported_at`,
                ADD COLUMN `usage_interval_exclusive_end` DATETIME(3) NULL AFTER `usage_interval_inclusive_start`,
                MODIFY COLUMN `order_count` INT NULL,
                ADD UNIQUE INDEX `pickware_usage_report.uidx.interval_start` (`usage_interval_inclusive_start`);
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_usage_report_order`
                    -- This index is used to efficiently find unassigned usage report orders (usage_report_id IS NULL)
                    -- within a specific date range (ordered_at) and for counting orders per usage report.
                    ADD INDEX `pickware_usage_report_order.idx.usage_report_id_ordered_at` (`usage_report_id`, `ordered_at`),
                    ADD COLUMN `order_created_at` DATETIME(3) NULL AFTER `ordered_at`,
                    ADD COLUMN `order_created_at_hour` DATETIME(3) NULL AFTER `order_created_at`,
                    -- This index is on the application-maintained column that pre-calculates the truncated hour of the
                    -- order creation time. It enables a fast equi-join when assigning orders to their hourly usage reports.
                    ADD INDEX `pickware_usage_report_order.idx.order_created_at_hour` (`order_created_at_hour`);
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
