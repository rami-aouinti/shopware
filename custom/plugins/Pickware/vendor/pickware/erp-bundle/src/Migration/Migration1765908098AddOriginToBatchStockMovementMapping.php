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

class Migration1765908098AddOriginToBatchStockMovementMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765908098;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_batch_stock_movement_mapping`
                ADD COLUMN `origin` VARCHAR(255) NOT NULL DEFAULT "user_created" AFTER `quantity`
        ');

        // Drop the default from origin column
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_batch_stock_movement_mapping`
                ALTER COLUMN `origin` DROP DEFAULT
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
