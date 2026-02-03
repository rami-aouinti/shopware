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

class Migration1764250457AddPickingStatisticsOverviewIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764250457;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_pick_event`
             ADD INDEX `pickware_wms_pick_event.idx.overview_stats` (
                `user_reference_id`,
                `pick_created_at`,
                `pick_created_at_day`,
                `pick_created_at_hour`,
                `picked_quantity`
            );',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
