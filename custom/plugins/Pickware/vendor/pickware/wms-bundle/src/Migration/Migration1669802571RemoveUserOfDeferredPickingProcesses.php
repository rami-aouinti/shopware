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

class Migration1669802571RemoveUserOfDeferredPickingProcesses extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1669802571;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `pickware_wms_picking_process` `picking_process`
            LEFT JOIN `state_machine_state`
                ON `picking_process`.`state_id` = `state_machine_state`.`id`
            SET `picking_process`.`user_id` = NULL
            WHERE `state_machine_state`.`technical_name` = "deferred"',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
