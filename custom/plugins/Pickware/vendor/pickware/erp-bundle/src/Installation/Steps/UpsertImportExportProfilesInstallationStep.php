<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Installation\Installer\ImportExportProfileInstaller;

class UpsertImportExportProfilesInstallationStep
{
    /**
     * @deprecated Will be removed in v5.0.0. Use the {@link ImportExportProfileInstaller} instead.
     */
    public function __construct(
        private readonly Connection $db,
        private readonly array $profileTechnicalNames,
    ) {}

    public function install(): void
    {
        foreach ($this->profileTechnicalNames as $profileTechnicalName) {
            $this->db->executeStatement(
                'INSERT INTO `pickware_erp_import_export_profile`
                        (`technical_name`, `log_retention_days`)
                        VALUES (:technicalName, :logRetentionDays)
                        ON DUPLICATE KEY UPDATE `technical_name` = `technical_name`',
                [
                    'technicalName' => $profileTechnicalName,
                    'logRetentionDays' => 720,
                ],
            );
        }
    }
}
