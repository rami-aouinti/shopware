<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1765974926AddPickCreatedAtLocaltime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765974926;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_pick_event`
             ADD COLUMN `pick_created_at_localtime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `pick_created_at_weekday`,
             ADD COLUMN `pick_created_at_localtime_timezone` VARCHAR(255) NOT NULL DEFAULT \'Europe/Berlin\' AFTER `pick_created_at_localtime`,
             ADD COLUMN `pick_created_at_localtime_hour` TINYINT GENERATED ALWAYS AS (HOUR(`pick_created_at_localtime`)) STORED AFTER `pick_created_at_localtime_timezone`,
             ADD COLUMN `pick_created_at_localtime_weekday` TINYINT GENERATED ALWAYS AS (WEEKDAY(`pick_created_at_localtime`)) STORED AFTER `pick_created_at_localtime_hour`;',
        );

        $connection->executeStatement(
            'UPDATE `pickware_wms_pick_event`
             SET
                `pick_created_at_localtime` = CONVERT_TZ(`pick_created_at`, \'+00:00\', \'+01:00\');',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_pick_event`
            MODIFY COLUMN `pick_created_at_localtime` DATETIME NOT NULL;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_pick_event`
            MODIFY COLUMN `pick_created_at_localtime_timezone` VARCHAR(255) NOT NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
