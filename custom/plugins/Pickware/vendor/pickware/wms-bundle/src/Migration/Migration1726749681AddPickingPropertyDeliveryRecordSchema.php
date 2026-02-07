<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1726749681AddPickingPropertyDeliveryRecordSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726749681;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_wms_picking_property_delivery_record` (
                    `id` BINARY(16) NOT NULL,
                    `product_id` BINARY(16) NOT NULL,
                    `product_version_id` BINARY(16) NOT NULL,
                    `product_snapshot` JSON NOT NULL,
                    `delivery_id` BINARY(16) NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `pickware_wms_picking_property_delivery_record.fk.product`
                        FOREIGN KEY (`product_id`, `product_version_id`)
                        REFERENCES `product` (`id`, `version_id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_wms_picking_property_delivery_record.fk.delivery`
                        FOREIGN KEY (`delivery_id`)
                        REFERENCES `pickware_wms_delivery` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_wms_picking_property_delivery_record_value` (
                    `id` BINARY(16) NOT NULL,
                    `picking_property_delivery_record_id` BINARY(16) NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `value` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `pickware_wms_picking_property_delivery_record_value.product`(`picking_property_delivery_record_id`, `name`),
                    CONSTRAINT `pickware_wms_picking_property_delivery_record_value.fk.record`
                        FOREIGN KEY (`picking_property_delivery_record_id`)
                        REFERENCES `pickware_wms_picking_property_delivery_record` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }
}
