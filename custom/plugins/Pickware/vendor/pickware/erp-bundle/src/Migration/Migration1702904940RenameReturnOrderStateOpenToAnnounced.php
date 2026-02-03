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

class Migration1702904940RenameReturnOrderStateOpenToAnnounced extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1702904940;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                UPDATE `state_machine_state`
                INNER JOIN `state_machine`
                    ON `state_machine_state`.`state_machine_id` = `state_machine`.`id`
                SET state_machine_state.`technical_name` = 'received'
                WHERE
                    `state_machine_state`.`technical_name` = 'open'
                    AND `state_machine`.`technical_name` = 'pickware_erp_return_order.state'
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
