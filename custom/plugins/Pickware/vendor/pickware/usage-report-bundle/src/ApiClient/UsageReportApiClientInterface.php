<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\UsageReportBundle\ApiClient\Model\PickwareShop;
use Pickware\UsageReportBundle\ApiClient\Model\UsageReport;
use Pickware\UsageReportBundle\ApiClient\Model\UsageReportRegistrationResponse;

interface UsageReportApiClientInterface
{
    /**
     * @param ImmutableCollection<UsageReport> $usageReports
     * @return ImmutableCollection<UsageReportRegistrationResponse>
     */
    public function registerUsageReports(
        ImmutableCollection $usageReports,
        PickwareShop $pickwareShop,
        ?string $licenseUuid,
    ): ImmutableCollection;
}
