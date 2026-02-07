<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731657303MigrateShippingMethodConfigForExportInformation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731657303;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `pickware_shipping_shipping_method_config`
                SET `shipment_config` = JSON_INSERT(`shipment_config`, "$.sendExportInformation", true)
                WHERE `carrier_technical_name` = "sendcloud"',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
