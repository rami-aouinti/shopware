<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\UsageReport\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ReportUsageTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'pickware.usage_report_bundle.report_usage';
    }

    public static function getDefaultInterval(): int
    {
        // This task should run every 60 minutes (in seconds).
        return 60 * 60;
    }
}
