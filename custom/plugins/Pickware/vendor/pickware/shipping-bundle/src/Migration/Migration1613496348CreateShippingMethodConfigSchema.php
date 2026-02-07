<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Migration;

use Doctrine\DBAL\Connection;
use function Pickware\InstallationLibrary\Migration\ensureCorrectCollationOfColumnForForeignKeyConstraint;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1613496348CreateShippingMethodConfigSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1613496348;
    }

    public function update(Connection $db): void
    {
        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $db,
            'pickware_shipping_carrier',
            'technical_name',
        );
        $db->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_shipping_shipping_method_config` (
                `id` BINARY(16) NOT NULL,
                `shipping_method_id` BINARY(16) NOT NULL,
                `carrier_technical_name` VARCHAR(16) NOT NULL,
                `shipment_config` LONGTEXT NOT NULL CHECK (json_valid(`shipment_config`)),
                `parcel_packing_configuration` LONGTEXT NOT NULL CHECK (json_valid(`parcel_packing_configuration`)),
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `pickware_shipping_shipping_method_config.uk.shipping_method` (`shipping_method_id`),
                CONSTRAINT `pickware_shipping_shipping_method_config.fk.carrier`
                    FOREIGN KEY (`carrier_technical_name`)
                    REFERENCES `pickware_shipping_carrier` (`technical_name`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_shipping_shipping_method_config.fk.shipping_method`
                    FOREIGN KEY (`shipping_method_id`)
                    REFERENCES `shipping_method` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
