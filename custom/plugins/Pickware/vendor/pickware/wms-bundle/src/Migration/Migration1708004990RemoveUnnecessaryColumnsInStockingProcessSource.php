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

class Migration1708004990RemoveUnnecessaryColumnsInStockingProcessSource extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1708004990;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_stocking_process_source`
            DROP FOREIGN KEY `pw_wms_stocking_process_source.fk.warehouse`,
            DROP FOREIGN KEY `pw_wms_stocking_process_source.fk.order`,
            DROP FOREIGN KEY `pw_wms_stocking_process_source.fk.return_order`,
            DROP FOREIGN KEY `pw_wms_stocking_process_source.fk.special_stock_location`,
            DROP COLUMN `warehouse_id`,
            DROP COLUMN `order_id`,
            DROP COLUMN `order_version_id`,
            DROP COLUMN `return_order_id`,
            DROP COLUMN `return_order_version_id`,
            DROP COLUMN `special_stock_location_technical_name`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
