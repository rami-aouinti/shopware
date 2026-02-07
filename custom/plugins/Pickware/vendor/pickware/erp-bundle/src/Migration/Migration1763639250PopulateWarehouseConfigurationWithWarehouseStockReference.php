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

class Migration1763639250PopulateWarehouseConfigurationWithWarehouseStockReference extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763639250;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `pickware_erp_product_warehouse_configuration` `pwc`
                INNER JOIN `pickware_erp_warehouse_stock` `ws`
                    ON `pwc`.`product_id` = `ws`.`product_id`
                    AND `pwc`.`product_version_id` = `ws`.`product_version_id`
                    AND `pwc`.`warehouse_id` = `ws`.`warehouse_id`
                SET `pwc`.`warehouse_stock_id` = `ws`.`id`
	            WHERE `pwc`.`warehouse_stock_id` IS NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
