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

class Migration1729775537AddProductSupplierConfigurationDevToPurchaseListItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729775537;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_purchase_list_item`
                ADD COLUMN `product_supplier_configuration_dev_id` BINARY(16) DEFAULT NULL,
                ADD UNIQUE INDEX `pickware_erp_purchase_list_item.uidx.product_supplier_conf` (`product_supplier_configuration_dev_id`),
                ADD CONSTRAINT `pickware_erp_purchase_list_item.fk.product_supplier_conf`
                    FOREIGN KEY (`product_supplier_configuration_dev_id`)
                    REFERENCES `pickware_erp_product_supplier_configuration_dev` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;
                SQL
        );
    }
}
