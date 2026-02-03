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
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Pickware\PickwareWms\Statistic\Dto\OutputAnalysis;
use Pickware\PickwareWms\Statistic\Dto\OutputAnalysisData;
use Pickware\PickwareWms\Statistic\Dto\PickingStatisticFilter;
use Pickware\PickwareWms\Statistic\Dto\StatisticValue;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;

class OutputAnalysisCalculator
{
    // 15-minute buckets ensure UTC buckets align with local hour/day boundaries for non-hour offsets.
    private const TIME_BUCKET_MINUTES = 15;

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function calculateOutputAnalysis(
        PickingStatisticFilter $filter,
    ): OutputAnalysis {
        $timeZone = $filter->timeZone;
        $aggregatedData = $this->aggregateOutputAnalysisData($filter, $timeZone);

        $byWeekday = $this->buildTimeSeries(
            $aggregatedData['byWeekday'],
            $aggregatedData['weekdayOccurrences'],
            0,
            6,
        );
        $byHour = $this->buildTimeSeries(
            $aggregatedData['byHour'],
            $aggregatedData['hourOccurrences'],
            0,
            23,
        );

        return OutputAnalysis::fromData($byWeekday, $byHour);
    }

    /**
     * @return array{
     *     byWeekday: array<int, array{picks: float, picked_units: float, deliveries: float, active_users: float}>,
     *     byHour: array<int, array{picks: float, picked_units: float, deliveries: float, active_users: float}>,
     *     weekdayOccurrences: array<int, int>,
     *     hourOccurrences: array<int, int>
     * }
     */
    private function aggregateOutputAnalysisData(PickingStatisticFilter $filter, DateTimeZone $timeZone): array
    {
        [
            'pickWhere' => $pickWhere,
            'deliveryWhere' => $deliveryWhere,
            'pickParams' => $pickParams,
            'deliveryParams' => $deliveryParams,
            'pickTypes' => $pickTypes,
            'deliveryTypes' => $deliveryTypes,
        ] = $this->buildEventQueryParts($filter);

        $pickBucketExpression = $this->buildTimeBucketExpression('pe.pick_created_at');
        $pickSql = <<<SQL
            SELECT
                {$pickBucketExpression} AS bucket,
                pe.user_reference_id,
                COUNT(*) AS picks,
                SUM(pe.picked_quantity) AS picked_units
            FROM pickware_wms_pick_event pe
            WHERE {$pickWhere}
            GROUP BY bucket, pe.user_reference_id
            SQL;

        $deliveryBucketExpression = $this->buildTimeBucketExpression('e.event_created_at');
        $deliverySql = <<<SQL
            SELECT
                {$deliveryBucketExpression} AS bucket,
                e.user_reference_id,
                COUNT(*) AS deliveries
            FROM pickware_wms_delivery_lifecycle_event e
            WHERE {$deliveryWhere}
            GROUP BY bucket, e.user_reference_id
            SQL;

        $pickResults = $this->connection->executeQuery(
            $pickSql,
            $pickParams,
            $pickTypes,
        )->fetchAllAssociative();

        $deliveryResults = $this->connection->executeQuery(
            $deliverySql,
            $deliveryParams,
            $deliveryTypes,
        )->fetchAllAssociative();

        $byHourTotals = $this->initializeTotals(24);
        $byWeekdayTotals = $this->initializeTotals(7);
        $activeUsersByHourDay = [];
        $activeUsersByWeekdayDay = [];
        $activeDaysByWeekday = [];
        $activeDates = [];

        foreach ($pickResults as $row) {
            $localDateTime = $this->createLocalDateTimeFromBucket((string) $row['bucket'], $timeZone);
            $localHour = (int) $localDateTime->format('G');
            $localWeekday = (int) $localDateTime->format('N') - 1;
            $localDate = $localDateTime->format('Y-m-d');

            $byHourTotals[$localHour]['picks'] += (float) $row['picks'];
            $byHourTotals[$localHour]['picked_units'] += (float) $row['picked_units'];
            $byWeekdayTotals[$localWeekday]['picks'] += (float) $row['picks'];
            $byWeekdayTotals[$localWeekday]['picked_units'] += (float) $row['picked_units'];

            $this->markActiveUser($activeUsersByHourDay, $localDate, $localHour, (string) $row['user_reference_id']);
            $this->markActiveUser($activeUsersByWeekdayDay, $localDate, $localWeekday, (string) $row['user_reference_id']);
            $activeDaysByWeekday[$localWeekday][$localDate] = true;
            $activeDates[$localDate] = true;
        }

        foreach ($deliveryResults as $row) {
            $localDateTime = $this->createLocalDateTimeFromBucket((string) $row['bucket'], $timeZone);
            $localHour = (int) $localDateTime->format('G');
            $localWeekday = (int) $localDateTime->format('N') - 1;
            $localDate = $localDateTime->format('Y-m-d');

            $byHourTotals[$localHour]['deliveries'] += (float) $row['deliveries'];
            $byWeekdayTotals[$localWeekday]['deliveries'] += (float) $row['deliveries'];

            $this->markActiveUser($activeUsersByHourDay, $localDate, $localHour, (string) $row['user_reference_id']);
            $this->markActiveUser($activeUsersByWeekdayDay, $localDate, $localWeekday, (string) $row['user_reference_id']);
            $activeDaysByWeekday[$localWeekday][$localDate] = true;
            $activeDates[$localDate] = true;
        }

        foreach ($activeUsersByHourDay as $hours) {
            foreach ($hours as $hour => $users) {
                $byHourTotals[(int) $hour]['active_users'] += count($users);
            }
        }

        foreach ($activeUsersByWeekdayDay as $weekdays) {
            foreach ($weekdays as $weekday => $users) {
                $byWeekdayTotals[(int) $weekday]['active_users'] += count($users);
            }
        }

        $weekdayOccurrences = array_fill(0, 7, 0);
        foreach ($activeDaysByWeekday as $weekday => $dates) {
            $weekdayOccurrences[(int) $weekday] = count($dates);
        }

        $hourOccurrences = array_fill(0, 24, count($activeDates));

        return [
            'byWeekday' => $byWeekdayTotals,
            'byHour' => $byHourTotals,
            'weekdayOccurrences' => $weekdayOccurrences,
            'hourOccurrences' => $hourOccurrences,
        ];
    }

    /**
     * @return array{
     *   pickWhere: string,
     *   deliveryWhere: string,
     *   pickParams: array<string, mixed>,
     *   deliveryParams: array<string, mixed>,
     *   pickTypes: array<string, string>,
     *   deliveryTypes: array<string, string>
     * }
     */
    private function buildEventQueryParts(PickingStatisticFilter $filter): array
    {
        $fromDateTime = $filter->timePeriod->fromDateTime;
        $toDateTime = $filter->timePeriod->toDateTime;

        $pickFilters = $filter->buildWhereClausesForTable(
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
        $deliveryFilters = $filter->buildWhereClausesForTable(
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

        return [
            'pickWhere' => implode(' AND ', $pickFilters['conditions']),
            'deliveryWhere' => implode(' AND ', $deliveryFilters['conditions']),
            'pickParams' => $pickFilters['params'],
            'deliveryParams' => $deliveryFilters['params'],
            'pickTypes' => $pickFilters['types'],
            'deliveryTypes' => $deliveryFilters['types'],
        ];
    }

    private function calculateAverage(float $total, int $occurrences): float
    {
        if ($occurrences === 0) {
            return 0.0;
        }

        return $total / $occurrences;
    }

    /**
     * @param array<int, array{picks: float, picked_units: float, deliveries: float, active_users: float}> $totalsByGroup
     * @param array<int, int> $occurrencesByGroup
     * @return array<int, OutputAnalysisData>
     */
    private function buildTimeSeries(
        array $totalsByGroup,
        array $occurrencesByGroup,
        int $startGroup,
        int $endGroup,
    ): array {
        $timeSeries = [];
        for ($group = $startGroup; $group <= $endGroup; $group++) {
            $occurrences = $occurrencesByGroup[$group] ?? 0;
            $totals = $totalsByGroup[$group] ?? [
                'picks' => 0.0,
                'picked_units' => 0.0,
                'deliveries' => 0.0,
                'active_users' => 0.0,
            ];
            $timeSeries[$group] = new OutputAnalysisData(
                picks: StatisticValue::fromFloat($this->calculateAverage($totals['picks'], $occurrences)),
                pickedUnits: StatisticValue::fromFloat($this->calculateAverage($totals['picked_units'], $occurrences)),
                deliveries: StatisticValue::fromFloat($this->calculateAverage($totals['deliveries'], $occurrences)),
                activeUsers: StatisticValue::fromFloat($this->calculateAverage($totals['active_users'], $occurrences)),
            );
        }

        return $timeSeries;
    }

    /**
     * @return array<int, array{picks: float, picked_units: float, deliveries: float, active_users: float}>
     */
    private function initializeTotals(int $size): array
    {
        $totals = [];
        for ($index = 0; $index < $size; $index++) {
            $totals[$index] = [
                'picks' => 0.0,
                'picked_units' => 0.0,
                'deliveries' => 0.0,
                'active_users' => 0.0,
            ];
        }

        return $totals;
    }

    /**
     * @param array<string, array<int, array<string, true>>> $activeUsersByDay
     */
    private function markActiveUser(array &$activeUsersByDay, string $date, int $group, string $userReferenceId): void
    {
        $activeUsersByDay[$date][$group][$userReferenceId] = true;
    }

    private function createLocalDateTimeFromBucket(string $bucket, DateTimeZone $timeZone): DateTimeImmutable
    {
        $utcDateTime = new DateTimeImmutable($bucket, new DateTimeZone('UTC'));

        return $utcDateTime->setTimezone($timeZone);
    }

    private function buildTimeBucketExpression(string $dateTimeColumn): string
    {
        return sprintf(
            "DATE_FORMAT(DATE_SUB(%s, INTERVAL MOD(MINUTE(%s), %d) MINUTE), '%%Y-%%m-%%d %%H:%%i:00')",
            $dateTimeColumn,
            $dateTimeColumn,
            self::TIME_BUCKET_MINUTES,
        );
    }
}
