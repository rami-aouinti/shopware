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
use Pickware\UsageReportBundle\Model\UsageReportOrderType;

class UsageReportOrderUpdater
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function assignUsageReportsToUsageReportOrdersWithoutUsageReports(DateTimeImmutable $periodStartDate): void
    {
        // Assign orders to usage reports by matching the pre-computed order_created_at_hour column to the usage report's
        // interval start.
        $this->connection->executeStatement(
            <<<SQL
                UPDATE `pickware_usage_report_order` usageReportOrder
                INNER JOIN `pickware_usage_report_usage_report` usageReport
                    ON usageReport.usage_interval_inclusive_start = usageReportOrder.order_created_at_hour
                    AND usageReport.reported_at IS NULL
                SET usageReportOrder.usage_report_id = usageReport.id
                WHERE usageReportOrder.usage_report_id IS NULL
                    AND usageReportOrder.ordered_at >= :periodStartDate
                    AND usageReportOrder.order_type = :orderType
                SQL,
            [
                'periodStartDate' => $periodStartDate->format('Y-m-d H:i:s.v'),
                'orderType' => UsageReportOrderType::Regular->value,
            ],
        );
    }
}
