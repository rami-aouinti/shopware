<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class PickingStatisticDateTimeOfEarliestLogEntryCalculator
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function calculateDateTimeOfEarliestLogEntry(): ?DateTimeImmutable
    {
        $result = $this->connection->fetchOne('
            SELECT MIN(min_created_at) AS earliest_event_created_at
            FROM (
                SELECT MIN(event_created_at) as min_created_at
                FROM pickware_wms_delivery_lifecycle_event
                UNION ALL
                SELECT MIN(pick_created_at) as min_created_at
                FROM pickware_wms_pick_event
                UNION ALL
                SELECT MIN(event_created_at) as min_created_at
                FROM pickware_wms_picking_process_lifecycle_event
            ) AS min_dates;
        ');

        if (!$result) {
            return null;
        }

        return new DateTimeImmutable($result);
    }
}
