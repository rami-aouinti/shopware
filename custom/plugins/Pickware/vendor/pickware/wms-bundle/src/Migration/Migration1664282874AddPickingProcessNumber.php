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

class Migration1664282874AddPickingProcessNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1664282874;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process`
            ADD COLUMN `number` VARCHAR(255) NULL',
        );

        $connection->executeStatement(
            'UPDATE `pickware_wms_picking_process`
            SET `number` = LEFT(LOWER(HEX(`id`)), 9)
            WHERE `number` IS NULL',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process`
            CHANGE COLUMN `number` `number` VARCHAR(255) NOT NULL',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
