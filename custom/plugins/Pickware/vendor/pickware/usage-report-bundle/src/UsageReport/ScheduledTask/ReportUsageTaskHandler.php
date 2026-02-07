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

use Pickware\UsageReportBundle\UsageReport\UsageReportService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ReportUsageTask::class)]
class ReportUsageTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        private readonly ?UsageReportService $usageReportService,
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if ($this->usageReportService === null) {
            // The usage report service must be constructed by a dependent bundle.
            // In case that has not happened yet (e.g., because no usage report configuration is available yet),
            // we cannot report usage.
            return;
        }

        $this->usageReportService->reportUsage();
    }
}
