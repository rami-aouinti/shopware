<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1619101148CreateCashRegisterSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1619101148;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_pos_cash_register` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `branch_store_id` BINARY(16) NOT NULL,
                `device_uuid` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pw_pos_cash_register.uidx.device_uuid` (`device_uuid`),
                CONSTRAINT `pw_pos_branch_store.fk.branch_store`
                    FOREIGN KEY (`branch_store_id`)
                    REFERENCES `pickware_pos_branch_store` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_pos_cash_register_fiskaly_configuration` (
                `id` BINARY(16) NOT NULL,
                `cash_register_id` BINARY(16) NOT NULL,
                `client_uuid` VARCHAR(255) NOT NULL,
                `tss_uuid` VARCHAR(255) NOT NULL,
                `business_platform_uuid` VARCHAR(255) NOT NULL,
                `version` varchar(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pw_pos_cash_register_fiskaly_configuration.uidx.cash_register_id` (`cash_register_id`),
                UNIQUE INDEX `pw_pos_cash_register_fiskaly_configuration.uidx.client_uuid` (`client_uuid`),
                UNIQUE INDEX `pw_pos_cash_register_fiskaly_configuration.uidx.bp_uuid` (`business_platform_uuid`),
                CONSTRAINT `pw_pos_cash_register_fiskaly_configuration.fk.cash_register`
                    FOREIGN KEY (`cash_register_id`)
                    REFERENCES `pickware_pos_cash_register` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
            )',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
