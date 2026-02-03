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

class Migration1607589491ReferenceOrderInsteadOfOrderDeliveryPositionInAggregatedStock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607589491;
    }

    public function update(Connection $connection): void
    {
        // We can work around migrating existing rows by simply dropping the table and repopulating it by doing a full
        // indexing run later.
        $connection->executeStatement('TRUNCATE TABLE `pickware_erp_stock`');

        // Add new columns, drop old columns
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
            ADD COLUMN `order_id` BINARY(16) NULL
                AFTER `bin_location_id`,
            ADD COLUMN `order_version_id` BINARY(16) NULL
                AFTER `order_id`,
            ADD UNIQUE INDEX `pickware_erp_stock.uidx.product.order` (
                `product_id`,
                `order_id`,
                `order_version_id`
            ),
            ADD INDEX `pickware_erp_stock_location.idx.order` (
                `order_id`,
                `order_version_id`
            ),
            ADD CONSTRAINT `pickware_erp_stock.fk.order`
                FOREIGN KEY (
                    `order_id`,
                    `order_version_id`
                )
                REFERENCES `order` (`id`, `version_id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            DROP INDEX `pickware_erp_stock.uidx.product.order_delivery_position`,
            DROP FOREIGN KEY `pickware_erp_stock.fk.order_delivery_position`,
            DROP COLUMN order_delivery_position_id,
            DROP COLUMN order_delivery_position_version_id;',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
