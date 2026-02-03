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

class Migration1764939635ConvertDeliveryParcelTrackingCodeToMappingTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764939635;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery_parcel_tracking_code`
                ADD COLUMN `tracking_code_id` BINARY(16) NULL AFTER `delivery_parcel_id`',
        );

        // Create temporary table with index on tracking_code and tracking_url_hash to be able to join the WMS tracking
        // codes on the shipping tracking codes efficiently.
        $connection->executeStatement(
            "CREATE TEMPORARY TABLE temp_tracking_code_mapping (
                `id` BINARY(16) NOT NULL,
                `tracking_code` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `tracking_url_hash` CHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                PRIMARY KEY (`tracking_code`, `tracking_url_hash`),
                KEY `idx_id` (`id`)
            ) ENGINE=InnoDB",
        );

        // Populate the temporary table
        $connection->executeStatement(
            "INSERT INTO temp_tracking_code_mapping
            SELECT
                `id`,
                `tracking_code`,
                MD5(COALESCE(`tracking_url`, '')) as `tracking_url_hash`
            FROM (
                SELECT
                    `id`,
                    `tracking_code`,
                    `tracking_url`,
                    ROW_NUMBER() OVER (
                        PARTITION BY `tracking_code`, MD5(COALESCE(`tracking_url`, ''))
                        ORDER BY `created_at` DESC
                    ) AS `rowNumber`
                FROM `pickware_shipping_tracking_code`
            ) AS ranked
            WHERE `rowNumber` = 1",
        );

        // Populate the new column in WMS with the newest matching tracking code from the shipping tracking code table
        $connection->executeStatement(
            "UPDATE `pickware_wms_delivery_parcel_tracking_code` AS `mapping`
            LEFT JOIN temp_tracking_code_mapping AS `tempTrackingCodeMapping`
                ON `tempTrackingCodeMapping`.`tracking_code` = `mapping`.`code`
                AND `tempTrackingCodeMapping`.`tracking_url_hash` = MD5(COALESCE(`mapping`.`tracking_url`, ''))
            SET `mapping`.`tracking_code_id` = `tempTrackingCodeMapping`.`id`",
        );

        $connection->executeStatement('DROP TEMPORARY TABLE temp_tracking_code_mapping');

        $connection->executeStatement(
            'DELETE FROM `pickware_wms_delivery_parcel_tracking_code`
            WHERE `tracking_code_id` IS NULL',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery_parcel_tracking_code`
                DROP PRIMARY KEY,
                DROP COLUMN `id`,
                DROP COLUMN `code`,
                DROP COLUMN `tracking_url`,
                DROP COLUMN `created_at`,
                DROP COLUMN `updated_at`,
                MODIFY COLUMN `tracking_code_id` BINARY(16) NOT NULL,
                ADD PRIMARY KEY (`delivery_parcel_id`, `tracking_code_id`)',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery_parcel_tracking_code`
                ADD CONSTRAINT `pickware_wms_delivery_parcel_tracking_code.fk.tracking_code`
                    FOREIGN KEY (`tracking_code_id`)
                    REFERENCES `pickware_shipping_tracking_code` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
