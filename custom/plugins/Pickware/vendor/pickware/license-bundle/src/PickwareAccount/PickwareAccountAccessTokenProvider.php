<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\PickwareAccount;

use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\PickwareAccountBundle\ApiClient\PickwareAccountAccessTokenProviderInterface;
use Shopware\Core\Framework\Context;

class PickwareAccountAccessTokenProvider implements PickwareAccountAccessTokenProviderInterface
{
    public function __construct(
        private readonly PluginInstallationRepository $pluginInstallationRepository,
    ) {}

    public function getAccessToken(): ?string
    {
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation(
            Context::createDefaultContext(),
        );

        return $pluginInstallation->getPickwareAccountAccessToken();
    }
}
