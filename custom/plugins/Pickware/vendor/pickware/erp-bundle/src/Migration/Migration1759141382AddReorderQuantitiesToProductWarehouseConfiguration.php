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

class Migration1759141382AddReorderQuantitiesToProductWarehouseConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759141382;
    }

    public function update(Connection $db): void
    {
        $db->executeStatement(
            'ALTER TABLE `pickware_erp_product_warehouse_configuration`
                ADD COLUMN `warehouse_stock_id` BINARY(16) NULL AFTER `warehouse_id`,
                ADD COLUMN `reorder_point` INT NULL AFTER `default_bin_location_id`,
                ADD COLUMN `maximum_quantity` INT NULL AFTER `reorder_point`,
                ADD COLUMN `stock_below_reorder_point` INT NULL AFTER `maximum_quantity`,
                ADD CONSTRAINT `fk.pickware_erp_product_warehouse_configuration.warehouse_stock`
                    FOREIGN KEY (`warehouse_stock_id`)
                    REFERENCES `pickware_erp_warehouse_stock` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;',
        );

        // Populate the new foreign key column with existing relations
        $db->executeStatement(
            'UPDATE `pickware_erp_product_warehouse_configuration` `pwc`
                INNER JOIN `pickware_erp_warehouse_stock` `ws`
                    ON `pwc`.`product_id` = `ws`.`product_id`
                    AND `pwc`.`product_version_id` = `ws`.`product_version_id`
                    AND `pwc`.`warehouse_id` = `ws`.`warehouse_id`
                SET `pwc`.`warehouse_stock_id` = `ws`.`id`;',
        );
    }

    public function updateDestructive(Connection $db): void {}
}
