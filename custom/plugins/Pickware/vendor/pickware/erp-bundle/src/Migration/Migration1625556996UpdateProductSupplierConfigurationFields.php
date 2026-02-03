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

class Migration1625556996UpdateProductSupplierConfigurationFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625556996;
    }

    public function update(Connection $connection): void
    {
        // Change column type VARCHAR(11) to VARCHAR(64) which is the same as the product.product_number
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_product_supplier_configuration`
            CHANGE COLUMN `supplier_product_number` `supplier_product_number` VARCHAR(64) NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
