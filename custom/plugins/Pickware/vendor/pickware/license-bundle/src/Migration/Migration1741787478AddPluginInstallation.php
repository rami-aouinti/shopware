<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1741787478AddPluginInstallation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1741787478;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE IF NOT EXISTS `pickware_license_bundle_plugin_installation` (
                    `id` BINARY(16) NOT NULL,
                    `installation_id` BINARY(16) NOT NULL,
                    # Re-add hyphens tho the UUID V4 stored in the `installation_id` column
                    `installation_uuid` VARCHAR(36) GENERATED ALWAYS AS (
                        LOWER(CONCAT(
                            LEFT(HEX(installation_id), 8), '-',
                            SUBSTRING(HEX(installation_id), 9, 4), '-',
                            SUBSTRING(HEX(installation_id), 13, 4), '-',
                            SUBSTRING(HEX(installation_id), 17, 4), '-',
                            RIGHT(HEX(installation_id), 12)
                        ))
                    ) VIRTUAL,
                    `pickware_account_access_token` VARCHAR(255) NULL,
                    `pickware_shop_uuid` VARCHAR(255) NULL,
                    `pickware_license_uuid` VARCHAR(255) NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
