<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LockingBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1743760940AddLockTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1743760940;
    }

    public function update(Connection $connection): void
    {
        // See \Symfony\Component\Lock\Store\DoctrineDbalStore::createTable
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE IF NOT EXISTS `pickware_locking_lock` (
                    `key_id` VARCHAR(64) NOT NULL,
                    `key_token` VARCHAR(44) NOT NULL,
                    `key_expiration` INT UNSIGNED NOT NULL,
                    PRIMARY KEY (`key_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
