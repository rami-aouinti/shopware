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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1649086118FixOptionalVersionedReferences extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649086118;
    }

    public function update(Connection $connection): void
    {
        $this->fixBrokenReference('pickware_erp_stock', 'order', 'order', $connection);
        $this->fixBrokenReference('pickware_erp_stock_movement', 'source_order', 'order', $connection);
        $this->fixBrokenReference('pickware_erp_stock_movement', 'destination_order', 'order', $connection);
        $this->fixBrokenReference('pickware_erp_supplier_order_line_item', 'product', 'product', $connection);
    }

    public function updateDestructive(Connection $connection): void {}

    private function fixBrokenReference(
        string $tableName,
        string $fieldName,
        string $referencedTable,
        Connection $connection,
    ): void {
        $connection->transactional(function(Connection $connection) use ($fieldName, $tableName, $referencedTable): void {
            // First null all broken references (because the reference was deleted)
            $connection->executeStatement(
                <<<SQL
                    UPDATE `{$tableName}`
                    LEFT JOIN `{$referencedTable}`
                        ON {$tableName}.`{$fieldName}_id` = `{$referencedTable}`.`id` AND `{$referencedTable}`.`version_id` = :liveVersionId
                    SET `{$tableName}`.`{$fieldName}_id` = NULL
                    WHERE `{$referencedTable}`.`id` IS NULL
                    SQL,
                ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
            );

            $connection->executeStatement(
                <<<SQL
                    UPDATE `{$tableName}`
                    SET `{$fieldName}_version_id` = :liveVersionId
                    WHERE `{$fieldName}_id` IS NOT NULL
                    SQL,
                ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
            );
        });
    }
}
