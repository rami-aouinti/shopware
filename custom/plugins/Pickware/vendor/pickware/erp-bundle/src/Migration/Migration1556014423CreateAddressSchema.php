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

class Migration1556014423CreateAddressSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556014423;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_address` (
                `id` BINARY(16) NOT NULL,
                `salutation_id` BINARY(16) NULL,
                `first_name` VARCHAR(255) NULL,
                `last_name` VARCHAR(255) NULL,
                `title` VARCHAR(255) NULL,
                `email` VARCHAR(255) NULL,
                `phone` VARCHAR(255) NULL,
                `company` VARCHAR(255) NULL,
                `position` VARCHAR(255) NULL,
                `street` VARCHAR(255) NULL,
                `house_number` VARCHAR(255) NULL,
                `address_addition` VARCHAR(255) NULL,
                `zip_code` VARCHAR(255) NULL,
                `city` VARCHAR(255) NULL,
                `country_iso` VARCHAR(255) NULL,
                `state` VARCHAR(255) NULL,
                `province` VARCHAR(255) NULL,
                `comment` TEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_address.fk.salutation`
                    FOREIGN KEY (`salutation_id`)
                    REFERENCES `salutation` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
