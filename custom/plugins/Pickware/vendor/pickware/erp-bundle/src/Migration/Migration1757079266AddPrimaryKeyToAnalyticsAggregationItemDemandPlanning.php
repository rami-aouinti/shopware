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

class Migration1757079266AddPrimaryKeyToAnalyticsAggregationItemDemandPlanning extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1757079266;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'pickware_erp_analytics_aggregation_item_demand_planning', 'id')) {
            // The original migration has been fixed due to a crash on systems with `sql_require_primary_key` = 1 so the
            // column might already exist.
            return;
        }

        // We can simply drop all existing data as it will be recalculated on the next demand planning run.
        $connection->executeStatement('DELETE FROM `pickware_erp_analytics_aggregation_item_demand_planning`');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_analytics_aggregation_item_demand_planning`
            ADD COLUMN `id` BINARY(16) NOT NULL FIRST,
            ADD PRIMARY KEY (`id`);
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
