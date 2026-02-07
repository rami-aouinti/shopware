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

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;

class UsageReportInitializer
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * @param positive-int $reportingPeriodInDays
     */
    public function ensureUsageReportsExistForPeriod(DateTimeImmutable $periodStartDate, int $reportingPeriodInDays): void
    {
        $valuePlaceholderStrings = [];
        $parameters = [];

        for ($day = 0; $day < $reportingPeriodInDays; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $intervalStart = $periodStartDate
                    ->add(DateInterval::createFromDateString("{$day} days"))
                    ->add(DateInterval::createFromDateString("{$hour} hours"));
                $intervalEnd = $intervalStart->add(DateInterval::createFromDateString('1 hour'));

                $valuePlaceholderStrings[] = '(' . SqlUuid::UUID_V4_GENERATION . ', ?, ?, UTC_TIMESTAMP(3))';
                $parameters[] = $intervalStart->format('Y-m-d H:i:s.v');
                $parameters[] = $intervalEnd->format('Y-m-d H:i:s.v');
            }
        }

        // Ignore duplicate entries if a usage report for the same interval already exists
        $this->connection->executeStatement(
            'INSERT IGNORE INTO `pickware_usage_report_usage_report` (
                `id`,
                `usage_interval_inclusive_start`,
                `usage_interval_exclusive_end`,
                `created_at`
            ) VALUES ' . implode(', ', $valuePlaceholderStrings),
            $parameters,
        );
    }
}
