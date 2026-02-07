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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1732115485AddCashRegisterTransactionNumberPrefixField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732115485;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            ALTER TABLE `pickware_pos_cash_register`
            ADD COLUMN `transaction_number_prefix` INT(11) NULL AFTER `fiscalization_configuration`,
            ADD UNIQUE INDEX `pickware_pos_cash_register.uidx.transaction_number_prefix` (`transaction_number_prefix`);
            SQL);
    }

    public function updateDestructive(Connection $connection): void {}
}
