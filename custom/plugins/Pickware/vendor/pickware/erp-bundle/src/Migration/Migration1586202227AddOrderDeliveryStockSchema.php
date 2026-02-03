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

class Migration1586202227AddOrderDeliveryStockSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586202227;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock_movement`
                ADD COLUMN `source_order_delivery_position_id` BINARY(16) NULL
                    AFTER `source_bin_location_id`,
                ADD COLUMN `source_order_delivery_position_version_id` BINARY(16) NULL
                    AFTER `source_order_delivery_position_id`,
                ADD COLUMN `destination_order_delivery_position_id` BINARY(16) NULL
                    AFTER `destination_bin_location_id`,
                ADD COLUMN `destination_order_delivery_position_version_id` BINARY(16) NULL
                    AFTER `destination_order_delivery_position_id`,
                ADD INDEX `pickware_erp_stock_movement.idx.source_order_delivery` (
                    `source_order_delivery_position_id`,
                    `source_order_delivery_position_version_id`
                ),
                ADD INDEX `pickware_erp_stock_movement.idx.dest_order_delivery` (
                    `destination_order_delivery_position_id`,
                    `destination_order_delivery_position_version_id`
                ),
                ADD CONSTRAINT `pickware_erp_stock_movement.fk.source_order_delivery`
                    FOREIGN KEY (
                        `source_order_delivery_position_id`,
                        `source_order_delivery_position_version_id`
                    )
                    REFERENCES `order_delivery_position` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                ADD CONSTRAINT `pickware_erp_stock_movement.fk.dest_order_delivery`
                    FOREIGN KEY (
                        `destination_order_delivery_position_id`,
                        `destination_order_delivery_position_version_id`
                    )
                    REFERENCES `order_delivery_position` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
                ADD COLUMN `order_delivery_position_id` BINARY(16) NULL AFTER `bin_location_id`,
                ADD COLUMN `order_delivery_position_version_id` BINARY(16) NULL AFTER `order_delivery_position_id`,
                ADD UNIQUE INDEX `pickware_erp_stock.uidx.product.order_delivery_position` (
                    `product_id`,
                    `order_delivery_position_id`,
                    `order_delivery_position_version_id`
                ),
                ADD INDEX `pickware_erp_stock_location.idx.order_delivery` (
                    `order_delivery_position_id`,
                    `order_delivery_position_version_id`
                ),
                ADD CONSTRAINT `pickware_erp_stock.fk.order_delivery_position`
                    FOREIGN KEY (`order_delivery_position_id`, `order_delivery_position_version_id`)
                    REFERENCES `order_delivery_position` (`id`, `version_id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
