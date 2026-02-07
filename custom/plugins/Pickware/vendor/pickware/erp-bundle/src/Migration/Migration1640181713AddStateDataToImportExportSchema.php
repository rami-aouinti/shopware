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

class Migration1640181713AddStateDataToImportExportSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1640181713;
    }

    public function update(Connection $connection): void
    {
        // JSON fields cannot be assigned a default value in MySQL 5.7. To add a non-nullable JSON column we need to
        // create a nullable column, insert default data for existing rows, then make it non-nullable.
        // https://dev.mysql.com/doc/refman/5.7/en/data-type-defaults.html#data-types-defaults-explicit
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_import_export`
            ADD COLUMN `state_data` JSON NULL DEFAULT NULL AFTER `state`;',
        );
        $connection->executeStatement('UPDATE `pickware_erp_import_export` SET `state_data` = "{}";');
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_import_export`
            CHANGE COLUMN `state_data` `state_data` JSON NOT NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
