<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1767003100FixLocaltimePrecisionForStatisticTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1767003100;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_lifecycle_event`
            MODIFY COLUMN `event_created_at_localtime` DATETIME(3) NOT NULL;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery_lifecycle_event`
            MODIFY COLUMN `event_created_at_localtime` DATETIME(3) NOT NULL;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_pick_event`
            MODIFY COLUMN `pick_created_at_localtime` DATETIME(3) NOT NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
