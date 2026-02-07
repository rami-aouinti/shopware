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
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1596645397CorrectNegativeStocks extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1596645397;
    }

    public function update(Connection $connection): void
    {
        // "stock_correction" is necessary for the migration, so create it here instead of in the installer
        $sql = '
            INSERT INTO `pickware_erp_special_stock_location` (
                `technical_name`,
                `created_at`
            ) VALUES (
                "stock_correction",
                UTC_TIMESTAMP(3)
            )
        ';
        $connection->executeStatement($sql);

        // Stock indexing will be performed after the update
        $sql = '
            INSERT INTO `pickware_erp_stock_movement` (
                `id`,
                `quantity`,
                `product_id`,
                `product_version_id`,
                `source_description`,
                `destination_description`,
                `source_location_type_technical_name`,
                `source_special_stock_location_technical_name`,
                `destination_location_type_technical_name`,
                `destination_warehouse_id`,
                `destination_bin_location_id`,
                `destination_order_delivery_position_id`,
                `destination_order_delivery_position_version_id`,
                `destination_special_stock_location_technical_name`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                -1 * `quantity`,
                `product_id`,
                `product_version_id`,
                "",
                "",
                "special_stock_location",
                "stock_correction",
                `location_type_technical_name`,
                `warehouse_id`,
                `bin_location_id`,
                `order_delivery_position_id`,
                `order_delivery_position_version_id`,
                `special_stock_location_technical_name`,
                UTC_TIMESTAMP(3)
            FROM `pickware_erp_stock`
            WHERE `quantity` < 0
            AND `location_type_technical_name` != "special_stock_location"
        ';
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void {}
}
