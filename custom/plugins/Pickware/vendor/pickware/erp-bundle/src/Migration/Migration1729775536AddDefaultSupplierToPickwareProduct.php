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

class Migration1729775536AddDefaultSupplierToPickwareProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729775536;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_pickware_product`
                ADD COLUMN `default_supplier_id` BINARY(16) DEFAULT NULL,
                ADD INDEX `pickware_erp_pickware_product.idx.default_supplier` (
                    `default_supplier_id`
                ),
                ADD CONSTRAINT `pickware_erp_pickware_product.fk.default_supplier`
                    FOREIGN KEY (`default_supplier_id`)
                    REFERENCES `pickware_erp_supplier` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;
                SQL
        );
    }
}
