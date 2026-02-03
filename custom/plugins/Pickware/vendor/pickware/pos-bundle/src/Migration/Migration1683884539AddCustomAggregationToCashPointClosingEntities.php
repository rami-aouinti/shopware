<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1683884539AddCustomAggregationToCashPointClosingEntities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1683884539;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing`
            ADD COLUMN `custom_aggregation` JSON NULL DEFAULT NULL AFTER `cash_register_id`;',
        );
        $connection->executeStatement(
            'UPDATE `pickware_pos_cash_point_closing`
            SET `custom_aggregation` = "{}";',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing`
            CHANGE COLUMN `custom_aggregation` `custom_aggregation` JSON NOT NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
