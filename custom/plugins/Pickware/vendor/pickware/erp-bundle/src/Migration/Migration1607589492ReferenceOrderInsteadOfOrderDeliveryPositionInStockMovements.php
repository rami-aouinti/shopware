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

class Migration1607589492ReferenceOrderInsteadOfOrderDeliveryPositionInStockMovements extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607589492;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock_movement`
            ADD COLUMN `source_order_id` BINARY(16) NULL
                AFTER `source_bin_location_id`,
            ADD COLUMN `source_order_version_id` BINARY(16) NULL
                AFTER `source_order_id`,
            ADD COLUMN `destination_order_id` BINARY(16) NULL
                AFTER `destination_bin_location_id`,
            ADD COLUMN `destination_order_version_id` BINARY(16) NULL
                AFTER `destination_order_id`,
            ADD INDEX `pickware_erp_stock_movement.idx.source_order` (
                `source_order_id`,
                `source_order_version_id`
            ),
            ADD CONSTRAINT `pickware_erp_stock_movement.fk.source_order`
                    FOREIGN KEY (
                        `source_order_id`,
                        `source_order_version_id`
                    )
                    REFERENCES `order` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
            ADD INDEX `pickware_erp_stock_movement.idx.dest_order` (
                `destination_order_id`,
                `destination_order_version_id`
            ),
            ADD CONSTRAINT `pickware_erp_stock_movement.fk.dest_order`
                    FOREIGN KEY (
                        `destination_order_id`,
                        `destination_order_version_id`
                    )
                    REFERENCES `order` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;',
        );

        // Migrate rows
        $connection->executeStatement(
            'UPDATE `pickware_erp_stock_movement` stockMovement
            LEFT JOIN `order_delivery_position` sourceOrderDeliveryPosition
                ON sourceOrderDeliveryPosition.id = stockMovement.source_order_delivery_position_id
                AND sourceOrderDeliveryPosition.version_id = stockMovement.source_order_delivery_position_version_id
            LEFT JOIN `order_delivery` sourceOrderDelivery
                ON sourceOrderDelivery.id = sourceOrderDeliveryPosition.order_delivery_id
                AND sourceOrderDelivery.version_id = sourceOrderDeliveryPosition.order_delivery_version_id
            LEFT JOIN `order_delivery_position` destinationOrderDeliveryPosition
                ON destinationOrderDeliveryPosition.id = stockMovement.destination_order_delivery_position_id
                AND destinationOrderDeliveryPosition.version_id = stockMovement.destination_order_delivery_position_version_id
            LEFT JOIN `order_delivery` destinationOrderDelivery
                ON destinationOrderDelivery.id = destinationOrderDeliveryPosition.order_delivery_id
                AND destinationOrderDelivery.version_id = destinationOrderDeliveryPosition.order_delivery_version_id
            SET
                stockMovement.source_order_id = sourceOrderDelivery.order_id,
                stockMovement.source_order_version_id = sourceOrderDelivery.order_version_id,
                stockMovement.destination_order_id = destinationOrderDelivery.order_id,
                stockMovement.destination_order_version_id = destinationOrderDelivery.order_version_id;',
        );

        // Drop old columns
        // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
        // (false positive) columns and indexes are dropped right after dropping the foreign keys the the names are different
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock_movement`
            DROP FOREIGN KEY `pickware_erp_stock_movement.fk.source_order_delivery`,
            DROP FOREIGN KEY `pickware_erp_stock_movement.fk.dest_order_delivery`,
            DROP COLUMN source_order_delivery_position_id,
            DROP COLUMN source_order_delivery_position_version_id,
            DROP COLUMN destination_order_delivery_position_id,
            DROP COLUMN destination_order_delivery_position_version_id;',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
