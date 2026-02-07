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

class Migration1744709240AddDeviceToPickingProcess extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1744709240;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process`
            ADD COLUMN `device_id` BINARY(16) NULL AFTER `pre_collecting_stock_container_id`,
            ADD CONSTRAINT `pickware_wms_picking_process.fk.device`
                FOREIGN KEY (`device_id`)
                REFERENCES `pickware_wms_device` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
