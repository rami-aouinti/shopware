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

class Migration1673271134AddCashPointClosingTransactionVatTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673271134;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing_transaction`
            ADD COLUMN `vat_table` JSON NULL AFTER `comment`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
