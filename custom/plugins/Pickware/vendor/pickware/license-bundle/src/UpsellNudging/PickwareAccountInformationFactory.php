<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\UpsellNudging;

use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\UpsellNudgingBundle\PickwareAccountInformation\PickwareAccountInformation;
use Shopware\Core\Framework\Context;

readonly class PickwareAccountInformationFactory
{
    public function __construct(private PluginInstallationRepository $pluginInstallationRepository) {}

    public function __invoke(): ?PickwareAccountInformation
    {
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation(
            Context::createDefaultContext(),
        );

        $pickwareLicense = $pluginInstallation->getPickwareLicense();
        if (!$pickwareLicense) {
            return null;
        }

        return new PickwareAccountInformation(
            shopUuid: $pickwareLicense->getShopUuid(),
            organizationUuid: $pickwareLicense->getOrganizationUuid(),
        );
    }
}
