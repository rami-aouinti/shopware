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

class Migration1765974930AddDeliveryLifecycleEventCreatedAtLocaltime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765974930;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery_lifecycle_event`
             ADD COLUMN `event_created_at_localtime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `event_created_at_weekday`,
             ADD COLUMN `event_created_at_localtime_timezone` VARCHAR(255) NOT NULL DEFAULT \'Europe/Berlin\' AFTER `event_created_at_localtime`,
             ADD COLUMN `event_created_at_localtime_hour` TINYINT GENERATED ALWAYS AS (HOUR(`event_created_at_localtime`)) STORED AFTER `event_created_at_localtime_timezone`,
             ADD COLUMN `event_created_at_localtime_weekday` TINYINT GENERATED ALWAYS AS (WEEKDAY(`event_created_at_localtime`)) STORED AFTER `event_created_at_localtime_hour`;',
        );

        $connection->executeStatement(
            'UPDATE `pickware_wms_delivery_lifecycle_event`
             SET
                `event_created_at_localtime` = CONVERT_TZ(`event_created_at`, \'+00:00\', \'+01:00\');',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery_lifecycle_event`
            MODIFY COLUMN `event_created_at_localtime` DATETIME NOT NULL;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery_lifecycle_event`
            MODIFY COLUMN `event_created_at_localtime_timezone` VARCHAR(255) NOT NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
