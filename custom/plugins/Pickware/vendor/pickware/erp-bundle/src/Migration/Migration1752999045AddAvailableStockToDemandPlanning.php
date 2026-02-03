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

class Migration1752999045AddAvailableStockToDemandPlanning extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752999045;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_analytics_aggregation_item_demand_planning`
            ADD COLUMN `available_stock` INT(11) NOT NULL DEFAULT 0 AFTER `reserved_stock`;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_demand_planning_list_item`
            ADD COLUMN `available_stock` INT(11) NOT NULL DEFAULT 0 AFTER `reserved_stock`;',
        );

        // Update existing rows
        $connection->executeStatement(
            'UPDATE `pickware_erp_analytics_aggregation_item_demand_planning`
            SET `available_stock` = `stock` - `reserved_stock`',
        );
        $connection->executeStatement(
            'UPDATE `pickware_erp_demand_planning_list_item`
            SET `available_stock` = `stock` - `reserved_stock`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
