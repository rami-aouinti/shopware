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

class Migration1718699455DisassociateImportExportElementFromLogEntry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1718699455;
    }

    public function update(Connection $connection): void
    {
        // Add new row column to import export log entry
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_import_export_log_entry`
            ADD COLUMN `row_number` INT(11) DEFAULT NULL;
        ');

        // Add row number to import export log entry where available
        $connection->executeStatement('
            UPDATE `pickware_erp_import_export_log_entry` AS log_entry
            JOIN `pickware_erp_import_export_element` AS import_export_element
                ON log_entry.import_export_element_id = import_export_element.id
            SET log_entry.row_number = import_export_element.row_number;
        ');

        // Drop foreign key constraint
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_import_export_log_entry`
            DROP FOREIGN KEY `pickware_erp_import_export_log_entry.fk.import_export_element`;
        ');

        // Drop the association between import export element and log entry
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_import_export_log_entry`
            DROP COLUMN `import_export_element_id`;
        ');

        // Delete all import export elements
        $connection->executeStatement('
            DELETE FROM `pickware_erp_import_export_element`;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
