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

class Migration1674558675AddTaxRulesToCashPointClosingTransactionLineItems extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1674558675;
    }

    public function update(Connection $connection): void
    {
        // Create new column
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing_transaction_line_item`
            ADD COLUMN `vat_table` JSON NULL AFTER `quantity`',
        );

        // Populate new column with values from the old column
        $connection->executeStatement(
            'UPDATE `pickware_pos_cash_point_closing_transaction_line_item`
            SET `pickware_pos_cash_point_closing_transaction_line_item`.`vat_table` = JSON_ARRAY(
                JSON_OBJECT(
                    "taxRate", `pickware_pos_cash_point_closing_transaction_line_item`.`tax_rate`,
                    "tax", JSON_EXTRACT(`pickware_pos_cash_point_closing_transaction_line_item`.`total`, "$.vat"),
                    "price", JSON_EXTRACT(`pickware_pos_cash_point_closing_transaction_line_item`.`total`, "$.inclVat")
                )
            );',
        );

        // Remove old column and set new column to required
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing_transaction_line_item`
             DROP FOREIGN KEY `pickware_pos_cash_transaction_line_item.fk.tax`,
             DROP COLUMN `tax_rate`,
             DROP COLUMN `tax_id`,
             CHANGE COLUMN `vat_table` `vat_table` JSON NOT NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
