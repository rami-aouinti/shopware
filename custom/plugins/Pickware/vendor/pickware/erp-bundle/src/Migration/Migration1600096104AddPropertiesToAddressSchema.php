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

class Migration1600096104AddPropertiesToAddressSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1600096104;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_address`
            ADD COLUMN `fax` VARCHAR(255) NULL AFTER `phone`,
            ADD COLUMN `website` VARCHAR(255) NULL AFTER `fax`,
            ADD COLUMN `vat_id` VARCHAR(255) NULL AFTER `comment`,
            ADD COLUMN `department` VARCHAR(255) NULL AFTER `company`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
