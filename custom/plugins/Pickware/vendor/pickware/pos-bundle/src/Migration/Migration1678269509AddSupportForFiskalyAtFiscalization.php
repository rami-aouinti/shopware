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

class Migration1678269509AddSupportForFiskalyAtFiscalization extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678269509;
    }

    public function update(Connection $connection): void
    {
        // Create new column `fiscalization_context`
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing_transaction`
            ADD COLUMN `fiscalization_context` JSON NULL AFTER `vat_table`',
        );

        // Populate `fiscalization_context` for all transactions successfully fiscalized with fiskaly DE
        $connection->executeStatement(
            'UPDATE `pickware_pos_cash_point_closing_transaction`
            SET `pickware_pos_cash_point_closing_transaction`.`fiscalization_context` = JSON_OBJECT(
                "fiskalyDe", JSON_OBJECT(
                    "clientUuid", `pickware_pos_cash_point_closing_transaction`.`cash_register_fiskaly_client_uuid`,
                    "result", JSON_OBJECT(
                        "tssTransactionUuid", `pickware_pos_cash_point_closing_transaction`.`fiskaly_tss_transaction_uuid`
                    )
                )
            )
            WHERE `cash_register_fiskaly_client_uuid` IS NOT NULL
            AND `fiskaly_tss_transaction_uuid` IS NOT NULL',
        );

        // Populate `fiscalization_context` for all erroneous transactions fiscalized with fiskaly DE
        $connection->executeStatement(
            'UPDATE `pickware_pos_cash_point_closing_transaction`
            SET `pickware_pos_cash_point_closing_transaction`.`fiscalization_context` = JSON_OBJECT(
                "fiskalyDe", JSON_OBJECT(
                    "clientUuid", `pickware_pos_cash_point_closing_transaction`.`cash_register_fiskaly_client_uuid`,
                    "result", JSON_OBJECT(
                        "error", `pickware_pos_cash_point_closing_transaction`.`fiskaly_tss_error_message`
                    )
                )
            )
            WHERE `cash_register_fiskaly_client_uuid` IS NOT NULL
            AND `fiskaly_tss_error_message` IS NOT NULL',
        );

        // Drop the now obsolete columns
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing_transaction`
             DROP COLUMN `cash_register_fiskaly_client_uuid`,
             DROP COLUMN `fiskaly_tss_transaction_uuid`,
             DROP COLUMN `fiskaly_tss_error_message`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
