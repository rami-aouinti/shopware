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

class Migration1756728919CreateDocumentTypeCustomFieldMappingTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756728919;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE IF NOT EXISTS `pickware_erp_document_type_custom_field_mapping` (
                    `id` BINARY(16) NOT NULL,
                    `document_type_id` BINARY(16) NOT NULL,
                    `custom_field_id` BINARY(16) NOT NULL,
                    `position` INT(11) NOT NULL,
                    `entity_type` ENUM ("order", "product") NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE INDEX `pckwr_erp_document_type_custom_field_mapping.uidx.document_type` (`document_type_id`, `custom_field_id`, `entity_type`),
                    CONSTRAINT `fk.pickware_erp_document_type_custom_field_mapping.document_type`
                        FOREIGN KEY (`document_type_id`)
                        REFERENCES `document_type` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    CONSTRAINT `fk.pckwr_erp_document_type_custom_field_mapping.custom_field_id`
                        FOREIGN KEY (`custom_field_id`)
                        REFERENCES `custom_field` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
