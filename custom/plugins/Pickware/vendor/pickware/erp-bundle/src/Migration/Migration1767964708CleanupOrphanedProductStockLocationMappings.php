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

class Migration1767964708CleanupOrphanedProductStockLocationMappings extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1767964708;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            DELETE mapping
            FROM `pickware_erp_product_stock_location_mapping` AS mapping
            LEFT JOIN `pickware_erp_product_stock_location_configuration` AS configuration
                ON mapping.`id` = configuration.`product_stock_location_mapping_id`
            WHERE mapping.`stock_id` IS NULL
              AND (
                  configuration.`id` IS NULL
                  OR (configuration.`reorder_point` = 0 AND configuration.`target_maximum_quantity` IS NULL)
              )
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
