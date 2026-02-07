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

class Migration1618321583AddExportFunctionality extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1618321583;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_import_export`
                ADD COLUMN `config` LONGTEXT NOT NULL CHECK (json_valid(`config`)),
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            RENAME TABLE `pickware_erp_import_element`
                TO `pickware_erp_import_export_element`
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
