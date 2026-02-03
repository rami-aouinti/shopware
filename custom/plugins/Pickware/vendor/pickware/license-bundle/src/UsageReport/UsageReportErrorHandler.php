<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\UsageReport;

use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\UsageReportBundle\ApiClient\UsageReportApiClientException;
use Pickware\UsageReportBundle\UsageReport\UsageReportErrorHandlerInterface;
use Shopware\Core\Framework\Context;

class UsageReportErrorHandler implements UsageReportErrorHandlerInterface
{
    public function __construct(
        private readonly PluginInstallationRepository $pluginInstallationRepository,
    ) {}

    public function handleUsageReportApiClientException(
        UsageReportApiClientException $exception,
        Context $context,
    ): void {
        $this->pluginInstallationRepository->update(
            ['latestUsageReportError' => $exception->serializeToJsonApiError()],
            $context,
        );
    }

    public function handleSuccessfulUsageReportRegistration(Context $context): void
    {
        $this->pluginInstallationRepository->update(
            ['latestUsageReportError' => null],
            $context,
        );
    }
}
