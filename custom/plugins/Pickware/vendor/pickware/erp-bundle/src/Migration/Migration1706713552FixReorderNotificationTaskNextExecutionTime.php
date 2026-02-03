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

use DateInterval;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1706713552FixReorderNotificationTaskNextExecutionTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1706713552;
    }

    public function update(Connection $connection): void
    {
        $systemConfigValue = $connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :configurationKey;',
            ['configurationKey' => 'PickwareErpStarter.global-plugin-config.reorderNotificationTime'],
        );
        if ($systemConfigValue === false) {
            return;
        }

        $nextExecutionTimeInUTC = DateTime::createFromFormat(
            'H:i:s',
            Json::decodeToArray($systemConfigValue)[ConfigJsonField::STORAGE_KEY],
            new DateTimeZone('UTC'),
        );
        if ($nextExecutionTimeInUTC === false) {
            return;
        }
        if ($nextExecutionTimeInUTC < new DateTime()) {
            $nextExecutionTimeInUTC->add(new DateInterval('P1D'));
        }
        $connection->executeStatement(
            'UPDATE `scheduled_task` SET `next_execution_time` = :nextExecutionTime WHERE `name` = :taskName;',
            [
                'taskName' => 'pickware_erp_reorder_notification',
                'nextExecutionTime' => $nextExecutionTimeInUTC->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
