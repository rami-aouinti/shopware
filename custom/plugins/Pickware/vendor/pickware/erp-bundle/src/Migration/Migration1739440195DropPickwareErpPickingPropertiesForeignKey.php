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

class Migration1739440195DropPickwareErpPickingPropertiesForeignKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1739440195;
    }

    public function update(Connection $connection): void
    {
        // The foreign key being dropped below was introduced in an earlier migration and references the "id" column.
        // However, MySQL 8.4 no longer supports foreign keys on non-unique columns. In our case, "id" is not unique by itself;
        // it is only unique in combination with "id_version".
        // As a result, we had to remove the foreign key from the migration. Additionally, we must ensure that the foreign key
        // is dropped from the database during updates to prevent errors when customers upgrade to MySQL 8.4.
        //
        // Important: We first need to check whether the foreign key still exists because i.e. some customers may have already
        // removed it manually.
        //
        // Further context: https://github.com/pickware/shopware-plugins/issues/8343

        $foreignKeyExists = $connection->fetchOne(
            'SELECT 1
                FROM `information_schema`.`TABLE_CONSTRAINTS`
                WHERE
                    `TABLE_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = "product"
                    AND `CONSTRAINT_NAME` = "pickware_erp.fk.pickwareErpPickingProperties"',
        );

        if (!$foreignKeyExists) {
            return;
        }

        // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
        // Orphaned indexes from this migration are cleaned up by Migration1761300578CleanupOrphanedIndicesFromForeignKeyDrops
        $connection->executeStatement(
            'ALTER TABLE `product` DROP FOREIGN KEY `pickware_erp.fk.pickwareErpPickingProperties`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
