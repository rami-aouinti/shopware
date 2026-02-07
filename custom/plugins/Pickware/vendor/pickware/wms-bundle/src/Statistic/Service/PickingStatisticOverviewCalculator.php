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
use Doctrine\DBAL\Exception;
use Pickware\PickwareWms\Statistic\Dto\PickingStatisticFilter;
use Pickware\PickwareWms\Statistic\Dto\PickingStatisticOverview;
use Pickware\PickwareWms\Statistic\Dto\StatisticValue;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;

class PickingStatisticOverviewCalculator
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function calculatePickStatisticOverview(
        PickingStatisticFilter $filter,
    ): PickingStatisticOverview {
        $overview = $this->createOverviewForPeriod($filter->timePeriod->fromDateTime, $filter->timePeriod->toDateTime, $filter);

        if ($filter->referencePeriod === null) {
            return $overview;
        }

        $referenceOverview = $this->createOverviewForPeriod(
            $filter->referencePeriod->fromDateTime,
            $filter->referencePeriod->toDateTime,
            $filter,
        );

        return $overview->withReferenceValues($referenceOverview);
    }

    private function createOverviewForPeriod(
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
        PickingStatisticFilter $filter,
    ): PickingStatisticOverview {
        $statisticForTimeRange = $this->calculatePickStatisticOverviewForFilter(
            $fromDateTime,
            $toDateTime,
            $filter,
        );

        return new PickingStatisticOverview(
            StatisticValue::fromFloat($statisticForTimeRange['picksPerWorkingHourPerUser']),
            StatisticValue::fromFloat($statisticForTimeRange['totalPicks']),
            StatisticValue::fromFloat($statisticForTimeRange['totalPickedQuantity']),
            StatisticValue::fromFloat($this->calculatePickedDeliveriesForFilter($fromDateTime, $toDateTime, $filter)),
        );
    }

    /**
     * @return array{picksPerWorkingHourPerUser: float, totalPicks: float, totalPickedQuantity: float}
     * @throws Exception
     */
    private function calculatePickStatisticOverviewForFilter(
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
        PickingStatisticFilter $filter,
    ): array {
        $filters = $filter->buildWhereClausesForTable(
            tableAlias: 'pe',
            createdAtColumnName: 'pick_created_at',
            userRoleTableName: 'pickware_wms_pick_event_user_role',
            userRoleEventIdColumnName: 'pick_id',
            fromDateTime: $fromDateTime,
            toDateTime: $toDateTime,
            additionalConditions: [],
            additionalParams: [],
            additionalTypes: [],
        );

        $where = implode(' AND ', $filters['conditions']);
        $activeHoursFilters = $filter->buildActiveHoursFilters($fromDateTime, $toDateTime);

        // Calculate picks per hour per user using weighted average to match the Performance Analysis calculation.
        // Weighted average: (total picks across all users) / (total active hours across all users)
        // Active hours are calculated from all event types (picks, deliveries, picking processes) to match
        // Performance Analysis, ensuring consistency between overview and detailed statistics.
        $sql = <<<SQL
                WITH active_time_per_user AS (
                    SELECT
                        distinct_active_hours.user_reference_id,
                        COUNT(*) AS active_hours
                    FROM (
                        SELECT
                            user_reference_id,
                            day,
                            hour
                        FROM (
                            SELECT
                                pe.user_reference_id,
                                pe.pick_created_at_day AS day,
                                pe.pick_created_at_hour AS hour
                            FROM pickware_wms_pick_event pe
                            WHERE {$activeHoursFilters['pickWhere']}
                            GROUP BY pe.user_reference_id, day, hour

                            UNION ALL

                            SELECT
                                de.user_reference_id,
                                de.event_created_at_day AS day,
                                de.event_created_at_hour AS hour
                            FROM pickware_wms_delivery_lifecycle_event de
                            WHERE {$activeHoursFilters['deliveryWhere']}
                            GROUP BY de.user_reference_id, day, hour

                            UNION ALL

                            SELECT
                                pp.user_reference_id,
                                pp.event_created_at_day AS day,
                                pp.event_created_at_hour AS hour
                            FROM pickware_wms_picking_process_lifecycle_event pp
                            WHERE {$activeHoursFilters['processWhere']}
                            GROUP BY pp.user_reference_id, day, hour
                        ) active_hour_rows
                        GROUP BY user_reference_id, day, hour
                    ) distinct_active_hours
                    GROUP BY distinct_active_hours.user_reference_id
                ),
                per_user AS (
                    SELECT
                        pe.user_reference_id,
                        COUNT(*) AS total_picks,
                        SUM(pe.picked_quantity) AS total_picked_quantity
                    FROM pickware_wms_pick_event pe
                    WHERE {$where}
                    GROUP BY pe.user_reference_id
                )
                SELECT
                    SUM(per_user.total_picks) AS totalPicks,
                    SUM(per_user.total_picked_quantity) AS totalPickedQuantity,
                    SUM(per_user.total_picks) / NULLIF(SUM(active_time_per_user.active_hours), 0) AS picksPerWorkingHourPerUser
                FROM per_user
                INNER JOIN active_time_per_user
                    ON per_user.user_reference_id = active_time_per_user.user_reference_id
            SQL;

        $params = array_merge($activeHoursFilters['params'], $filters['params']);
        $types = array_merge($activeHoursFilters['types'], $filters['types']);

        return array_map(
            fn($value) => (float) $value,
            $this->connection->fetchAssociative($sql, $params, $types),
        );
    }

    private function calculatePickedDeliveriesForFilter(
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
        PickingStatisticFilter $filter,
    ): int {
        $filters = $filter->buildWhereClausesForTable(
            tableAlias: 'e',
            createdAtColumnName: 'event_created_at',
            userRoleTableName: 'pickware_wms_delivery_lifecycle_event_user_role',
            userRoleEventIdColumnName: 'delivery_lifecycle_event_id',
            fromDateTime: $fromDateTime,
            toDateTime: $toDateTime,
            additionalConditions: ['e.event_technical_name = :eventName'],
            additionalParams: ['eventName' => DeliveryLifecycleEventType::Complete->value],
            additionalTypes: [],
        );

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*) AS total')
            ->from('pickware_wms_delivery_lifecycle_event', 'e')
            ->where(implode(' AND ', $filters['conditions']))
            ->setParameters($filters['params'], $filters['types']);

        return (int) $queryBuilder->fetchOne();
    }
}
