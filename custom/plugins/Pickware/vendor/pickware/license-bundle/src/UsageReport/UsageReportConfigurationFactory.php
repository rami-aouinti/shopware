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
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicenseLeaseSubscriptionState;
use Pickware\UsageReportBundle\ApiClient\Model\PickwareShop;
use Pickware\UsageReportBundle\Configuration\UsageReportConfiguration;
use Shopware\Core\Framework\Context;

class UsageReportConfigurationFactory
{
    public function __construct(
        private readonly PluginInstallationRepository $pluginInstallationRepository,
    ) {}

    public function __invoke(): ?UsageReportConfiguration
    {
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation(
            Context::createDefaultContext(),
        );
        $pickwareLicense = $pluginInstallation->getPickwareLicense();
        if ($pickwareLicense === null) {
            return null;
        }

        $pickwareLicenseLease = $pluginInstallation->getPickwareLicenseLease();
        if ($pickwareLicenseLease === null) {
            return null;
        }

        if ($pickwareLicenseLease->getSubscriptionState() === PickwareLicenseLeaseSubscriptionState::EXPIRED) {
            return null;
        }

        return new UsageReportConfiguration(
            pickwareShop: new PickwareShop(
                shopUuid: $pickwareLicense->getShopUuid(),
                organizationUuid: $pickwareLicense->getOrganizationUuid(),
            ),
            licenseUuid: $pickwareLicense->getLicenseUuid(),
        );
    }
}
