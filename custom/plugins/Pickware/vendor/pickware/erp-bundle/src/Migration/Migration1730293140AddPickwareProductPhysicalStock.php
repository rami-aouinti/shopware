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

class Migration1730293140AddPickwareProductPhysicalStock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730293140;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_pickware_product`
            ADD COLUMN `physical_stock` INT(11) NOT NULL DEFAULT 0 AFTER `reserved_stock`;',
        );

        $isStockInitialized = $connection->fetchOne(
            'SELECT `stock_initialized` FROM `pickware_erp_config` LIMIT 1',
        );
        if (!$isStockInitialized) {
            return;
        }

        $connection->executeStatement(
            'UPDATE `pickware_erp_pickware_product` `pickware_product`
            INNER JOIN `product`
                ON `product`.`id` = `pickware_product`.`product_id`
                AND `product`.`version_id` = `pickware_product`.`product_version_id`
            SET `pickware_product`.`physical_stock` = `product`.`stock`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
