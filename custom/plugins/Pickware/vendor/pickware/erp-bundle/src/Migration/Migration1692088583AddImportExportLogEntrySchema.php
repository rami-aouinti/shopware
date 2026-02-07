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

class Migration1692088583AddImportExportLogEntrySchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1692088583;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_import_export_log_entry` (
                `id` BINARY(16) NOT NULL,
                `import_export_id` BINARY(16) NOT NULL,
                `import_export_element_id` BINARY(16) NULL,
                `log_level` VARCHAR(255) NOT NULL,
                `message` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_import_export_log_entry.fk.import_export`
                    FOREIGN KEY (`import_export_id`)
                    REFERENCES `pickware_erp_import_export` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_import_export_log_entry.fk.import_export_element`
                    FOREIGN KEY (`import_export_element_id`)
                    REFERENCES `pickware_erp_import_export_element` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
