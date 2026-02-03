<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Installer;

use Doctrine\DBAL\Connection;

class ImportExportProfileInstaller
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function ensureImportExportProfile(string $technicalName, int $logRetentionDays): self
    {
        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_import_export_profile`
                (`technical_name`, `log_retention_days`)
                VALUES (:technicalName, :logRetentionDays)
                ON DUPLICATE KEY UPDATE `log_retention_days` = :logRetentionDays',
            [
                'technicalName' => $technicalName,
                'logRetentionDays' => $logRetentionDays,
            ],
        );

        return $this;
    }
}
