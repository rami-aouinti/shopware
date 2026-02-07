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

class Migration1682341260AddReturnOrderReason extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1682341260;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_return_order_line_item`
            ADD COLUMN `reason` VARCHAR(255) NOT NULL DEFAULT "unknown" AFTER `quantity`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
