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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class PickingStatisticUserSnapshotLoader
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * @param string[] $userIds
     * @return array<string, array{firstName: ?string, lastName: ?string, username: ?string}>
     */
    public function loadLatestUserSnapshots(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $query = <<<SQL
                WITH events AS (
                    SELECT
                        user_reference_id,
                        user_snapshot,
                        pick_created_at AS created_at
                    FROM pickware_wms_pick_event
                    WHERE user_reference_id IN (:userIds)

                    UNION ALL

                    SELECT
                        user_reference_id,
                        user_snapshot,
                        event_created_at AS created_at
                    FROM pickware_wms_delivery_lifecycle_event
                    WHERE user_reference_id IN (:userIds)

                    UNION ALL

                    SELECT
                        user_reference_id,
                        user_snapshot,
                        event_created_at AS created_at
                    FROM pickware_wms_picking_process_lifecycle_event
                    WHERE user_reference_id IN (:userIds)
                ),
                latest_events AS (
                    SELECT
                        user_reference_id,
                        MAX(created_at) AS max_created_at
                    FROM events
                    GROUP BY user_reference_id
                )
                SELECT
                    LOWER(HEX(events.user_reference_id)) AS user_reference_id,
                    JSON_UNQUOTE(JSON_EXTRACT(events.user_snapshot, '$.firstName')) AS firstName,
                    JSON_UNQUOTE(JSON_EXTRACT(events.user_snapshot, '$.lastName'))  AS lastName,
                    JSON_UNQUOTE(JSON_EXTRACT(events.user_snapshot, '$.username')) AS username
                FROM events
                JOIN latest_events
                  ON events.user_reference_id = latest_events.user_reference_id
                 AND events.created_at = latest_events.max_created_at
                WHERE events.user_reference_id IN (:userIds)
                GROUP BY events.user_reference_id
            SQL;

        return $this->connection->executeQuery(
            $query,
            ['userIds' => Uuid::fromHexToBytesList($userIds)],
            ['userIds' => ArrayParameterType::BINARY],
        )->fetchAllAssociativeIndexed();
    }
}
