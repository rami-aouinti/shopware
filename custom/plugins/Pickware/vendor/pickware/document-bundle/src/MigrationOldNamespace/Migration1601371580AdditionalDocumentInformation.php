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

class Migration1601371580AdditionalDocumentInformation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1601371580;
    }

    public function update(Connection $connection): void
    {
        // All added fields must be nullable or have default to be backwards compatible with old versions of the DHL
        // plugin.
        // As an example the migration Migration1575643478MoveDocumentsToShopwarePluginsDocumentBundle would fail
        // if one of this fields were nullable or won't have a default value
        $connection->executeStatement(
            'ALTER TABLE `pickware_document`
                CHANGE `mime_type` `mime_type` VARCHAR(255) NULL, -- make nullable
                CHANGE `page_format` `page_format` LONGTEXT NULL, -- make nullable
                ADD `file_size_in_bytes` BIGINT NOT NULL DEFAULT -1 AFTER `orientation`,
                ADD `file_name` VARCHAR(255) NULL AFTER `file_size_in_bytes`,
                ADD `path_in_private_file_system` VARCHAR(255) NULL AFTER `file_name`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
