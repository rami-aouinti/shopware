<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1745571516AddLogRetentionTimeToImportExportProfiles extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1745571516;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_import_export_profile`
            ADD COLUMN `log_retention_days` INT NULL DEFAULT 720 AFTER `technical_name`
        ');
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_import_export_profile`
            ALTER COLUMN `log_retention_days` DROP DEFAULT
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_import_export`
            ADD COLUMN `logs_truncated` TINYINT NOT NULL DEFAULT 0 AFTER `completed_at`
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
