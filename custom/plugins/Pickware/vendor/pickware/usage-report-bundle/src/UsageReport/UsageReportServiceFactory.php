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

use Pickware\DalBundle\EntityManager;
use Pickware\UsageReportBundle\ApiClient\UsageReportApiClientInterface;
use Pickware\UsageReportBundle\Configuration\UsageReportConfiguration;
use Pickware\UsageReportBundle\OrderReport\UsageReportOrderInitializer;
use Symfony\Component\Clock\ClockInterface;

class UsageReportServiceFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ClockInterface $timeProvider,
        private readonly UsageReportOrderInitializer $usageReportOrderInitializer,
        private readonly UsageReportInitializer $usageReportInitializer,
        private readonly UsageReportOrderUpdater $usageReportOrderUpdater,
        private readonly UsageReportUpdater $usageReportUpdater,
    ) {}

    public function __invoke(
        UsageReportApiClientInterface $usageReportApiClient,
        UsageReportErrorHandlerInterface $usageReportErrorHandler,
        int $reportingPeriodInDays,
        ?UsageReportConfiguration $usageReportConfiguration = null,
    ): ?UsageReportService {
        // Usage reporting can only be performed if a usage report configuration is available, e.g. after an on-premises
        // shop has connected their Pickware plugin to a Pickware Account.
        if ($usageReportConfiguration === null) {
            return null;
        }

        return new UsageReportService(
            $this->entityManager,
            $usageReportApiClient,
            $this->timeProvider,
            $this->usageReportOrderInitializer,
            $this->usageReportInitializer,
            $this->usageReportOrderUpdater,
            $this->usageReportUpdater,
            $usageReportErrorHandler,
            $usageReportConfiguration,
            $reportingPeriodInDays,
        );
    }
}
