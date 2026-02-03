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

class Migration1678269510RefactorCashRegisterFiscalizationConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678269510;
    }

    public function update(Connection $connection): void
    {
        // Create new column `fiscalization_configuration`
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_register`
            ADD COLUMN `fiscalization_configuration` JSON NULL AFTER `device_uuid`',
        );

        // Populate new column with values from the old table
        $connection->executeStatement(
            'UPDATE `pickware_pos_cash_register`
            LEFT JOIN `pickware_pos_cash_register_fiskaly_configuration`
                ON `pickware_pos_cash_register`.`id` = `pickware_pos_cash_register_fiskaly_configuration`.`cash_register_id`
            SET `pickware_pos_cash_register`.`fiscalization_configuration` = JSON_OBJECT(
                "fiskalyDe", JSON_OBJECT(
                    "clientUuid", `pickware_pos_cash_register_fiskaly_configuration`.`client_uuid`,
                    "tssUuid", `pickware_pos_cash_register_fiskaly_configuration`.`tss_uuid`,
                    "businessPlatformUuid", `pickware_pos_cash_register_fiskaly_configuration`.`business_platform_uuid`
                )
            )
            WHERE `pickware_pos_cash_register_fiskaly_configuration`.`id` IS NOT NULL',
        );

        // Remove old table
        $connection->executeStatement(
            'DROP TABLE `pickware_pos_cash_register_fiskaly_configuration`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
