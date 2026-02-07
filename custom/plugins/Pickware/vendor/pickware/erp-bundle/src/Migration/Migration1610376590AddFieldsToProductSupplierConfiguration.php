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

class Migration1610376590AddFieldsToProductSupplierConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610376590;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // Is already re-created in this migration and we do not touch it retrospectively.
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_product_supplier_configuration`
            CHANGE COLUMN `supplier_id` `supplier_id` BINARY(16) NULL,
            ADD COLUMN `supplier_product_number` VARCHAR(11) NULL AFTER `supplier_id`,
            ADD COLUMN `min_purchase` INT(11) NULL AFTER `supplier_product_number`,
            ADD COLUMN `purchase_steps` INT(11) NULL AFTER `min_purchase`,
            DROP FOREIGN KEY `pickware_erp_product_supplier_conf.fk.supplier`;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_product_supplier_configuration`
            ADD CONSTRAINT `pickware_erp_product_supplier_conf.fk.supplier`
                FOREIGN KEY (`supplier_id`)
                REFERENCES `pickware_erp_supplier` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
