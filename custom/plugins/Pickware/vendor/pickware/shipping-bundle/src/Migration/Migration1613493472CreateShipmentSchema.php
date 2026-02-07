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

class Migration1613493472CreateShipmentSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1613493472;
    }

    public function update(Connection $db): void
    {
        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $db,
            'pickware_shipping_carrier',
            'technical_name',
        );
        $db->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_shipping_shipment` (
                `id` BINARY(16) NOT NULL,
                `shipment_blueprint` JSON NOT NULL,
                `meta_information` JSON NULL,
                `carrier_technical_name` VARCHAR(255) DEFAULT NULL,
                `sales_channel_id` BINARY(16) DEFAULT NULL,
                `cancelled` TINYINT(1) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_shipping_shipment.fk.carrier`
                    FOREIGN KEY (`carrier_technical_name`)
                    REFERENCES `pickware_shipping_carrier` (`technical_name`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_shipping_shipment.fk.sales_channel`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $db->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_shipping_tracking_code` (
                `id` BINARY(16) NOT NULL,
                `tracking_code` VARCHAR(255) NOT NULL,
                `tracking_url` VARCHAR(255) DEFAULT NULL,
                `meta_information` JSON NOT NULL,
                `shipment_id` BINARY(16) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_shipping_tracking_code.fk.shipment`
                    FOREIGN KEY (`shipment_id`)
                    REFERENCES `pickware_shipping_shipment` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $db->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_shipping_document_shipment_mapping` (
                `shipment_id` BINARY(16) NOT NULL,
                `document_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`shipment_id`,`document_id`),
                CONSTRAINT `pickware_shipping_document_shipment_mapping.fk.document`
                    FOREIGN KEY (`document_id`)
                    REFERENCES `pickware_document` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_shipping_document_shipment_mapping.fk.shipment`
                    FOREIGN KEY (`shipment_id`)
                    REFERENCES `pickware_shipping_shipment` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $db->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_shipping_document_tracking_code_mapping` (
                `tracking_code_id` BINARY(16) NOT NULL,
                `document_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`tracking_code_id`,`document_id`),
                CONSTRAINT `pickware_shipping_document_tracking_code_mapping.fk.document`
                    FOREIGN KEY (`document_id`)
                    REFERENCES `pickware_document` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_shipping_document_tracking_code_mapping.fk.tracking_cod`
                    FOREIGN KEY (`tracking_code_id`)
                    REFERENCES `pickware_shipping_tracking_code` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $db->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_shipping_shipment_order_mapping` (
                `shipment_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`shipment_id`,`order_id`,`order_version_id`),
                CONSTRAINT `pickware_shipping_shipment_order_mapping.fk.order`
                    FOREIGN KEY (`order_id`,`order_version_id`)
                    REFERENCES `order` (`id`, `version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_shipping_shipment_order_mapping.fk.shipment`
                    FOREIGN KEY (`shipment_id`)
                    REFERENCES `pickware_shipping_shipment` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
