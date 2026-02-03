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

class Migration1672940274RenameCancelTransition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1672940274;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `state_machine_history`
            INNER JOIN `state_machine` ON `state_machine_history`.`state_machine_id` = `state_machine`.`id`
            SET `state_machine_history`.`action_name` = "cancel_"
            WHERE `state_machine_history`.`action_name` = "cancel"
                AND `state_machine`.`technical_name` = "pickware_wms.picking_process"',
        );
        $connection->executeStatement(
            'UPDATE `state_machine_transition`
            INNER JOIN `state_machine` ON `state_machine_transition`.`state_machine_id` = `state_machine`.`id`
            SET `state_machine_transition`.`action_name` = "cancel_"
            WHERE `state_machine_transition`.`action_name` = "cancel"
                AND `state_machine`.`technical_name` = "pickware_wms.picking_process"',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
