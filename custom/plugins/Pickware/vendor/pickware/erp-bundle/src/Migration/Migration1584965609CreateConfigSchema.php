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

class Migration1584965609CreateConfigSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1584965609;
    }

    public function update(Connection $db): void
    {
        $db->executeStatement(
            'CREATE TABLE `pickware_erp_config` (
                `id` BINARY(16) NOT NULL,
                `stock_initialized` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $db): void {}
}
