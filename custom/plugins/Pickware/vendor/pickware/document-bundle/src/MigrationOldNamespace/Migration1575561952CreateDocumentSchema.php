<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwarePlugins\DocumentBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1575561952CreateDocumentSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1575561952;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_document_type` (
                `technical_name` VARCHAR(255) NOT NULL,
                `description` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_document` (
                `id` BINARY(16) NOT NULL,
                `deep_link_code` CHAR(32) NOT NULL,
                `document_type_technical_name` VARCHAR(255) NOT NULL,
                `mime_type` VARCHAR(255) NOT NULL,
                `page_format` JSON NOT NULL,
                `orientation` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `fk.pickware_document.document_type_technical_name` (`document_type_technical_name`),
                CONSTRAINT `fk.pickware_document.document_type_technical_name`
                    FOREIGN KEY (`document_type_technical_name`)
                    REFERENCES `pickware_document_type` (`technical_name`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
