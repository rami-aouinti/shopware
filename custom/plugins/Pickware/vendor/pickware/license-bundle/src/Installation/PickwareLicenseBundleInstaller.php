<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\LicenseBundle\Installation\Steps\CreatePluginInstallationInstallationStep;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareLicenseBundleInstaller
{
    private function __construct(
        private readonly Connection $databaseConnection,
    ) {
        // Create an instance with ::initFromContainer() instead.
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        return new self(
            databaseConnection: $container->get(Connection::class),
        );
    }

    public function install(): void
    {
        (new CreatePluginInstallationInstallationStep($this->databaseConnection))->install();
    }
}
