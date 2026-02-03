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

class Migration1755242125AddExpectedAndActualDeliveryDateToSupplierOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1755242125;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            ALTER TABLE `pickware_erp_supplier_order`
            CHANGE COLUMN `delivery_date` `actual_delivery_date` DATETIME(3) NULL
            SQL);

        $connection->executeStatement(<<<SQL
            ALTER TABLE `pickware_erp_supplier_order`
            ADD COLUMN `expected_delivery_date` DATETIME(3) NULL AFTER `due_date`
            SQL);

        $connection->executeStatement(<<<SQL
            UPDATE `pickware_erp_supplier_order`
            SET `expected_delivery_date` = `actual_delivery_date`
            SQL);
    }

    public function updateDestructive(Connection $connection): void {}
}
