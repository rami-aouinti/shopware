<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1723889071CreateDatevAccountingDocumentGuidSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1723889071;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_datev_accounting_document_guid` (
                `id` BINARY(16) NOT NULL,
                `guid` VARCHAR(255) NOT NULL,
                `document_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_datev_accounting_document_guid.fk.document_id` FOREIGN KEY (`document_id`)
                    REFERENCES `document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');

        $connection->executeStatement('
            CREATE TABLE `pickware_datev_import_export_accounting_document_guid_mapping` (
                `import_export_id` BINARY(16) NOT NULL,
                `accounting_document_guid_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`import_export_id`, `accounting_document_guid_id`),
                CONSTRAINT `pickware_datev_export_receipt_guid_mapping.fk.import_export_id` FOREIGN KEY (`import_export_id`)
                    REFERENCES `pickware_erp_import_export` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_datev_export_accounting_document_mapping.fk.guid_id` FOREIGN KEY (`accounting_document_guid_id`)
                    REFERENCES `pickware_datev_accounting_document_guid` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
