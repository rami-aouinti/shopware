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

class Migration1764363646SplitReservedStockIntoInternalAndExternal extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764363646;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
                ALTER TABLE `pickware_erp_pickware_product`
                ADD COLUMN `internal_reserved_stock` INT(11) NOT NULL DEFAULT 0 AFTER `reserved_stock`,
                ADD COLUMN `external_reserved_stock` INT(11) NOT NULL DEFAULT 0 AFTER `internal_reserved_stock`;
            SQL);

        // We save the reserved stock in the internal reserved stock column, while this value can contain externally
        // managed reservations, this will be addressed by a separate code-based-migration.
        $connection->executeStatement(<<<SQL
                UPDATE `pickware_erp_pickware_product` SET `internal_reserved_stock` = `reserved_stock`;
            SQL);

        $connection->executeStatement(<<<SQL
                ALTER TABLE `pickware_erp_pickware_product`
                DROP COLUMN `reserved_stock`;
            SQL);

        $connection->executeStatement(<<<SQL
                ALTER TABLE `pickware_erp_pickware_product`
                ADD COLUMN `reserved_stock` INT(11) AS (`internal_reserved_stock` + `external_reserved_stock`) STORED AFTER `incoming_stock`;
            SQL);
    }

    public function updateDestructive(Connection $connection): void {}
}
