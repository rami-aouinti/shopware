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

class Migration1745500033AddShipAutomaticallyToPickwareProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1745500033;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_pickware_product`
            ADD COLUMN `ship_automatically` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stock_not_available_for_sale`
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
