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

use Pickware\UsageReportBundle\ApiClient\UsageReportApiClientException;
use Shopware\Core\Framework\Context;

interface UsageReportErrorHandlerInterface
{
    public function handleUsageReportApiClientException(
        UsageReportApiClientException $exception,
        Context $context,
    ): void;

    public function handleSuccessfulUsageReportRegistration(Context $context): void;
}
