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

class Migration1750948805AddUsageReportErrorToPluginInstallation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1750948805;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_license_bundle_plugin_installation`
                ADD `latest_usage_report_error` JSON NULL AFTER `latest_pickware_license_refresh_error`;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
