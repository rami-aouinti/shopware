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

class Migration1741856217AddSupplierOrderCustomFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1741856217;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order`
                ADD COLUMN `custom_fields` JSON NULL
                AFTER `internal_comment`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
