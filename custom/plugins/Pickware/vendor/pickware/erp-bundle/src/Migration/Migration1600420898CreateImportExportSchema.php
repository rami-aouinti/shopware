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

class Migration1600420898CreateImportExportSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1600420898;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_import_export_profile` (
                `technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_import_export` (
                `id` BINARY(16) NOT NULL,
                `type` VARCHAR(255) NOT NULL,
                `profile_technical_name` VARCHAR(255) NOT NULL,
                `user_id` BINARY(16) DEFAULT NULL,
                `user_comment` TEXT DEFAULT NULL,
                `state` VARCHAR(255) NOT NULL,
                `current_item` INT(11) DEFAULT NULL,
                `total_number_of_items` INT(11) DEFAULT NULL,
                `started_at` DATETIME DEFAULT NULL,
                `completed_at` DATETIME DEFAULT NULL,
                `errors` LONGTEXT NULL CHECK (json_valid(`errors`)),
                `document_id` BINARY(16) NULL DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_import_export.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_import_export.fk.document`
                    FOREIGN KEY (`document_id`)
                    REFERENCES `pickware_document` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_job.fk.profile`
                    FOREIGN KEY (`profile_technical_name`)
                    REFERENCES `pickware_erp_import_export_profile` (`technical_name`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_import_element` (
                `id` BINARY(16) NOT NULL,
                `import_export_id` BINARY(16) NOT NULL,
                `row_number` INT(11) NOT NULL,
                `row_data` LONGTEXT NOT NULL CHECK (json_valid(`row_data`)),
                `errors` LONGTEXT NULL CHECK (json_valid(`errors`)),
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_import_element.uniq.import_export_row_number`
                    (`import_export_id`, `row_number`),
                CONSTRAINT `pickware_erp_import_element.fk.import_export`
                    FOREIGN KEY (`import_export_id`)
                    REFERENCES `pickware_erp_import_export` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
