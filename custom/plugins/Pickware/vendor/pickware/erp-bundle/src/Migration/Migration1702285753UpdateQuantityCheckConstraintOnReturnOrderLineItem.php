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

class Migration1702285753UpdateQuantityCheckConstraintOnReturnOrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1702285753;
    }

    /**
     * Update the quantity check from quantity>0 to quantity>=0. Due to differences in the mariaDB and MySQL handling
     * for auto-generated checks in column definitions, we need to use a temporary swap column to update the check.
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_return_order_line_item`
            ADD COLUMN `temp_quantity` INT(11) NOT NULL;
        ');
        $connection->executeStatement('
            UPDATE `pickware_erp_return_order_line_item`
            SET `temp_quantity` = `quantity`;
        ');
        // Drop column with old constraint
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_return_order_line_item`
            DROP COLUMN `quantity`;
        ');
        // Add column anew without a constraint
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_return_order_line_item`
            ADD COLUMN `quantity` INT(11) NOT NULL;
        ');
        // Add explicit constraint (with name) so we can modify the constraint easier in the future.
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_return_order_line_item`
            ADD CONSTRAINT `avoid_negative_quantity` CHECK (`quantity` >= 0);
        ');
        $connection->executeStatement('
            UPDATE `pickware_erp_return_order_line_item`
            SET `quantity` = `temp_quantity`;
        ');
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_return_order_line_item`
            DROP COLUMN `temp_quantity`;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
