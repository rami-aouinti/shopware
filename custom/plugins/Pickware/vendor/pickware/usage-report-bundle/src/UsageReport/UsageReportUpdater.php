<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\UsageReport;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class UsageReportUpdater
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function updateOrderCountsForUnreportedUsageReports(
        DateTimeImmutable $periodStartDate,
        DateTimeImmutable $periodEndDate,
    ): void {
        $this->connection->executeStatement(
            <<<SQL
                UPDATE `pickware_usage_report_usage_report` usageReport
                SET usageReport.order_count = (
                    SELECT COUNT(*)
                    FROM `pickware_usage_report_order` usageReportOrder
                    WHERE usageReportOrder.usage_report_id = usageReport.id
                )
                WHERE usageReport.usage_interval_inclusive_start >= :periodStartDate
                    AND usageReport.usage_interval_inclusive_start < :periodEndDate
                    AND usageReport.reported_at IS NULL
                SQL,
            [
                'periodStartDate' => $periodStartDate->format('Y-m-d H:i:s.v'),
                'periodEndDate' => $periodEndDate->format('Y-m-d H:i:s.v'),
            ],
        );
    }
}
