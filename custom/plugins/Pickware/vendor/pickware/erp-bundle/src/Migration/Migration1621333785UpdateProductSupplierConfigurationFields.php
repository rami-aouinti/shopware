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

class Migration1621333785UpdateProductSupplierConfigurationFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1621333785;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `pickware_erp_product_supplier_configuration`
            SET `min_purchase` = 1
            WHERE `min_purchase` IS NULL;',
        );
        $connection->executeStatement(
            'UPDATE `pickware_erp_product_supplier_configuration`
            SET `purchase_steps` = 1
            WHERE `purchase_steps` IS NULL;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_product_supplier_configuration`
            CHANGE COLUMN `min_purchase` `min_purchase` INT(11) NOT NULL DEFAULT 1,
            CHANGE COLUMN `purchase_steps` `purchase_steps` INT(11) NOT NULL DEFAULT 1;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
