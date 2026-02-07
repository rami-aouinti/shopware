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

class Migration1619094846CreateBranchStoreSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1619094846;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_pos_branch_store` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `address_id` BINARY(16) NOT NULL,
                `fiskaly_organization_uuid` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_pos_branch_store.uidx.address` (`address_id`),
                UNIQUE INDEX `pickware_pos_branch_store.uidx.fiskaly_organization_uuid` (`fiskaly_organization_uuid`),
                CONSTRAINT `pickware_pos_branch_store.fk.sales_channel`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_branch_store.fk.address`
                    FOREIGN KEY (`address_id`)
                    REFERENCES `pickware_pos_address` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
