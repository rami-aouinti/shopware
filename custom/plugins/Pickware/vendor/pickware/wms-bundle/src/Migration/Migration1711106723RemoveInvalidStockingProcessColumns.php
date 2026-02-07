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

class Migration1711106723RemoveInvalidStockingProcessColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711106723;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_stocking_process_source`
            DROP FOREIGN KEY `pw_wms_stocking_process_source.fk.bin_location`,
            DROP FOREIGN KEY `pw_wms_stocking_process_source.fk.stock_container`,
            DROP COLUMN `bin_location_id`,
            DROP COLUMN `stock_container_id`;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_stocking_process`
            DROP FOREIGN KEY `pickware_wms_stocking_process.fk.stock_container`,
            DROP INDEX `pickware_wms_stocking_process.uidx.stock_container`,
            DROP COLUMN `stock_container_id`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
