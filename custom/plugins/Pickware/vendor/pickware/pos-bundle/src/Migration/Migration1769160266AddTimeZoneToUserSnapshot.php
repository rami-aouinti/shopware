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

class Migration1769160266AddTimeZoneToUserSnapshot extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769160266;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE
                pickware_pos_cash_point_closing
            LEFT JOIN user
                ON pickware_pos_cash_point_closing.user_id = user.id
            SET
                pickware_pos_cash_point_closing.user_snapshot = JSON_INSERT(
                    pickware_pos_cash_point_closing.user_snapshot,
                    "$.timeZone",
                    user.time_zone
                )
            WHERE
                JSON_EXTRACT(pickware_pos_cash_point_closing.user_snapshot, "$.timeZone") IS NULL
            ;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
