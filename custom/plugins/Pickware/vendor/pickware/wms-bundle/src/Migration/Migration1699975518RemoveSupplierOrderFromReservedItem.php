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

class Migration1699975518RemoveSupplierOrderFromReservedItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699975518;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                DELETE FROM `pickware_wms_picking_process_reserved_item`
                WHERE `supplier_order_id` IS NOT NULL
                SQL
        );

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_wms_picking_process_reserved_item`
                DROP FOREIGN KEY `pw_wms_picking_process_reserved_item.fk.supplier_order`,
                DROP COLUMN `supplier_order_id`
                SQL
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
