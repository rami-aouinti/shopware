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

class Migration1726749680AddPickingPropertyOrderRecordSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726749680;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_erp_picking_property_order_record` (
                    `id` BINARY(16) NOT NULL,
                    `product_id` BINARY(16),
                    `product_version_id` BINARY(16),
                    `product_snapshot` JSON NOT NULL,
                    `order_id` BINARY(16) NOT NULL,
                    `order_version_id` BINARY(16) NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `pickware_erp_picking_property_order_record.fk.product`
                        FOREIGN KEY (`product_id`, `product_version_id`)
                        REFERENCES `product` (`id`, `version_id`)
                        ON DELETE SET NULL
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_erp_picking_property_order_record.fk.order`
                        FOREIGN KEY (`order_id`, `order_version_id`)
                        REFERENCES `order` (`id`, `version_id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_erp_picking_property_order_record_value` (
                    `id` BINARY(16) NOT NULL,
                    `picking_property_order_record_id` BINARY(16) NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `value` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `pickware_erp_picking_property_order_record_value.product`(`picking_property_order_record_id`, `name`),
                    CONSTRAINT `pickware_erp_picking_property_order_record_value.fk.order_record`
                        FOREIGN KEY (`picking_property_order_record_id`)
                        REFERENCES `pickware_erp_picking_property_order_record` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    INDEX `pickware_erp_picking_property_order_record_value.idx.value` (`value`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }
}
