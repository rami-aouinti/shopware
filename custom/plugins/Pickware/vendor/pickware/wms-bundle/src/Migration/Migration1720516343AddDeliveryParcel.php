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
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1720516343AddDeliveryParcel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720516343;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_wms_delivery_parcel` (
                    `id` BINARY(16) NOT NULL,
                    `delivery_id` BINARY(16) NOT NULL,
                    `shipped` TINYINT(1) NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `pickware_wms_delivery_parcel.fk.delivery`
                        FOREIGN KEY (`delivery_id`)
                        REFERENCES `pickware_wms_delivery` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );

        $trackingCodes = $connection->fetchAllAssociative(
            <<<SQL
                SELECT * FROM `pickware_wms_delivery_tracking_code`;
                SQL,
        );
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_wms_delivery_parcel_tracking_code` (
                    `id` BINARY(16) NOT NULL,
                    `delivery_parcel_id` BINARY(16) NOT NULL,
                    `code` VARCHAR(255) NOT NULL,
                    `tracking_url` VARCHAR(255) NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `pickware_wms_delivery_parcel_tracking_code.fk.delivery_parcel`
                        FOREIGN KEY (`delivery_parcel_id`)
                        REFERENCES `pickware_wms_delivery_parcel` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );

        $connection->transactional(function(Connection $connection) use ($trackingCodes): void {
            foreach ($trackingCodes as $trackingCode) {
                $parcelId = Uuid::randomBytes();
                $connection->executeStatement(
                    <<<SQL
                        INSERT INTO `pickware_wms_delivery_parcel` (
                            `id`,
                            `delivery_id`,
                            `shipped`,
                            `created_at`,
                            `updated_at`
                        ) VALUES (
                            :id,
                            :deliveryId,
                            :shipped,
                            :createdAt,
                            :updatedAt
                        );
                        SQL,
                    [
                        'id' => $parcelId,
                        'deliveryId' => $trackingCode['delivery_id'],
                        'shipped' => $trackingCode['shipped'],
                        'createdAt' => $trackingCode['created_at'],
                        'updatedAt' => $trackingCode['updated_at'],
                    ],
                );

                $connection->executeStatement(
                    <<<SQL
                        INSERT INTO `pickware_wms_delivery_parcel_tracking_code` (
                            `id`,
                            `delivery_parcel_id`,
                            `code`,
                            `tracking_url`,
                            `created_at`,
                            `updated_at`
                        ) VALUES (
                            :id,
                            :deliveryParcelId,
                            :code,
                            :trackingUrl,
                            :createdAt,
                            :updatedAt
                        );
                        SQL,
                    [
                        'id' => $trackingCode['id'],
                        'deliveryParcelId' => $parcelId,
                        'code' => $trackingCode['code'],
                        'trackingUrl' => $trackingCode['tracking_url'],
                        'createdAt' => $trackingCode['created_at'],
                        'updatedAt' => $trackingCode['updated_at'],
                    ],
                );
            }
        });

        $connection->executeStatement(
            <<<SQL
                DROP TABLE `pickware_wms_delivery_tracking_code`;
                SQL,
        );
    }
}
