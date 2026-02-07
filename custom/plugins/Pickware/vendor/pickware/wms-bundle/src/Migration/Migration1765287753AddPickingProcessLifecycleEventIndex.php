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

class Migration1765287753AddPickingProcessLifecycleEventIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765287753;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_lifecycle_event`
             ADD INDEX `pw_wms_picking_process_lifecycle_event.idx.evt_tec_created` (
                `event_technical_name`,
                `event_created_at`
            );',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
