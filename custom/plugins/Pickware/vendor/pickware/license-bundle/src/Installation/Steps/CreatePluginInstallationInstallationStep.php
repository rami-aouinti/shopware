<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\LicenseBundle\PickwareLicenseBundle;

class CreatePluginInstallationInstallationStep
{
    public function __construct(
        private readonly Connection $databaseConnection,
    ) {}

    public function install(): void
    {
        $pluginInstallation = $this->databaseConnection->fetchOne(
            'SELECT COUNT(*) FROM `pickware_license_bundle_plugin_installation`',
        );
        if ($pluginInstallation) {
            return;
        }

        $this->databaseConnection->executeStatement(
            'INSERT INTO `pickware_license_bundle_plugin_installation` (
                `id`,
                `installation_id`,
                `created_at`
            ) VALUES (
                :id,
                ' . SqlUuid::UUID_V4_GENERATION . ',
                UTC_TIMESTAMP(3)
            ) ON DUPLICATE KEY UPDATE `id` = `id`',
            ['id' => hex2bin(mb_strtoupper(PickwareLicenseBundle::PLUGIN_INSTALLATION_ID))],
        );
    }
}
