<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Installation\Installer\ImportExportProfileInstaller;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertImportExportProfilesInstallationStep;
use Pickware\ProductSetBundle\ImportExportProfile\ProductSetConfigurationExporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareProductSetBundleInstaller
{
    private Connection $db;

    private function __construct()
    {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $self = new self();

        $self->db = $container->get(Connection::class);

        return $self;
    }

    public function install(): void
    {
        if (class_exists(ImportExportProfileInstaller::class)) {
            (new ImportExportProfileInstaller($this->db))
                ->ensureImportExportProfile(ProductSetConfigurationExporter::TECHNICAL_NAME, logRetentionDays: 30);
        } else {
            (new UpsertImportExportProfilesInstallationStep($this->db, [
                ProductSetConfigurationExporter::TECHNICAL_NAME,
            ]))->install();
        }
    }

    public function uninstall(): void {}
}
