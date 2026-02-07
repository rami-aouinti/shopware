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

class Migration1599039841CreateSupplierSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599039841;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_supplier` (
                `id` BINARY(16) NOT NULL,
                `number` VARCHAR(255) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `customer_number` VARCHAR(255) NULL,
                `default_delivery_time` INT(11) NULL,
                `language_id` BINARY(16) NOT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `address_id` BINARY(16) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_supplier.uidx.code` (`number`),
                UNIQUE INDEX `pickware_erp_supplier.uidx.name` (`name`),
                UNIQUE INDEX `pickware_erp_supplier.uidx.address` (`address_id`),
                CONSTRAINT `pickware_erp_supplier.fk.language`
                    FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_supplier.fk.currency`
                    FOREIGN KEY (`currency_id`)
                    REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_supplier.fk.address`
                    FOREIGN KEY (`address_id`)
                    REFERENCES `pickware_erp_address` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
