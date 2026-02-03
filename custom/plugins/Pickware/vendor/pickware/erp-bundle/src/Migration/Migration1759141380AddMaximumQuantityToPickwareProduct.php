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

class Migration1759141380AddMaximumQuantityToPickwareProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759141380;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_pickware_product`
                ADD COLUMN `maximum_quantity` INT NULL AFTER `reorder_point`,
                ADD COLUMN `stock_below_reorder_point` INT GENERATED ALWAYS AS (
                    IF(`reorder_point` IS NULL, NULL, `reorder_point` - `physical_stock`)
                ) VIRTUAL AFTER `maximum_quantity`;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
