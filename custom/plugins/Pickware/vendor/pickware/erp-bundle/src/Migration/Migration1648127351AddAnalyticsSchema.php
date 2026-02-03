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

class Migration1648127351AddAnalyticsSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648127351;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_analytics_profile` (
                `technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement(
            'CREATE TABLE `pickware_erp_analytics_session` (
                `id` BINARY(16) NOT NULL,
                `profile_technical_name` VARCHAR(255) NOT NULL,
                `configuration` JSON NOT NULL,
                `user_id` BINARY(16) NOT NULL,
                `last_calculation` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_analytics_session.fk.analytic`
                    FOREIGN KEY (`profile_technical_name`)
                    REFERENCES `pickware_erp_analytics_profile` (`technical_name`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_analytics_session.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
