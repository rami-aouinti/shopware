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
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareWms\Statistic\Dto\PerformanceAnalysisAggregatedUserStatisticValues;
use Pickware\PickwareWms\Statistic\Dto\PerformanceAnalysisAggregatedUserStatisticValuesPerGranularity;
use Pickware\PickwareWms\Statistic\Dto\PerformanceAnalysisUserStatisticValue;
use Pickware\PickwareWms\Statistic\Dto\PickingStatisticFilter;
use Pickware\PickwareWms\Statistic\Dto\StatisticValue;
use Pickware\PickwareWms\Statistic\PickingStatisticRounding;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

/**
 * @phpstan-type StatisticsQueryResult array{
 *     user_reference_id: string,
 *     user_snapshot: string,
 *     total: int,
 *     avg_per_day: float,
 *     avg_per_hour: float,
 * }
 */
abstract class AbstractPickingStatisticPerformanceAnalysisCalculator
{
    public const TOTAL_COLUMN_ALIAS = 'total';
    public const AVG_PER_DAY_COLUMN_ALIAS = 'avg_per_day';
    public const AVG_PER_HOUR_COLUMN_ALIAS = 'avg_per_hour';
    public const ACTIVE_DAYS_COLUMN_ALIAS = 'active_days';
    public const ACTIVE_HOURS_COLUMN_ALIAS = 'active_hours';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly PickingStatisticUserSnapshotLoader $userSnapshotLoader,
        private readonly string $tableName,
        private readonly string $userRoleTableName,
        private readonly string $userRoleEventIdColumnName,
        private readonly string $createdAtColumnName,
        private readonly string $createdAtDayColumnName,
        private readonly string $createdAtHourColumnName,
        private readonly string $valueSelectStatement = 'COUNT(*)',
    ) {}

    public function calculateStatisticPerformanceAnalysis(
        PickingStatisticFilter $filter,
        Context $context,
    ): PerformanceAnalysisAggregatedUserStatisticValuesPerGranularity {
        $fromDateTime = $filter->timePeriod->fromDateTime;
        $toDateTime = $filter->timePeriod->toDateTime;
        $performanceAnalysis = $this->calculateStatisticPerformanceAnalysisForPeriod($fromDateTime, $toDateTime, $filter, $context);

        if (!$filter->referencePeriod) {
            return $performanceAnalysis;
        }

        $performanceAnalysisForReferencePeriod = $this->calculateStatisticPerformanceAnalysisForPeriod(
            $filter->referencePeriod->fromDateTime,
            $filter->referencePeriod->toDateTime,
            $filter,
            $context,
        );

        return $performanceAnalysis->withReferenceValues($performanceAnalysisForReferencePeriod);
    }

    public function calculateStatisticPerformanceAnalysisForPeriod(
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
        PickingStatisticFilter $filter,
        Context $context,
    ): PerformanceAnalysisAggregatedUserStatisticValuesPerGranularity {
        $result = $this->fetchStatistics($filter, $fromDateTime, $toDateTime);

        $userIds = array_column($result, 'user_reference_id');

        /** @var ImmutableCollection<UserEntity> $userEntities */
        $userEntities = ImmutableCollection::create($this->entityManager->findBy(
            UserDefinition::class,
            ['id' => $userIds],
            $context,
        ));

        $userEntitiesById = [];
        foreach ($userEntities as $userEntity) {
            $userEntitiesById[$userEntity->getId()] = $userEntity;
        }

        $missingUserIds = array_values(array_diff($userIds, array_keys($userEntitiesById)));
        $userSnapshots = $missingUserIds === [] ? [] : $this->userSnapshotLoader->loadLatestUserSnapshots($missingUserIds);

        return new PerformanceAnalysisAggregatedUserStatisticValuesPerGranularity(
            performanceAnalysisAggregatedUserStatisticValuesPerHour: $this->buildStatisticsFromResult(
                $result,
                self::AVG_PER_HOUR_COLUMN_ALIAS,
                $userEntitiesById,
                $userSnapshots,
                $this->getWeightedAverageCalculator(self::ACTIVE_HOURS_COLUMN_ALIAS),
            ),
            performanceAnalysisAggregatedUserStatisticValuesPerDay: $this->buildStatisticsFromResult(
                $result,
                self::AVG_PER_DAY_COLUMN_ALIAS,
                $userEntitiesById,
                $userSnapshots,
                $this->getWeightedAverageCalculator(self::ACTIVE_DAYS_COLUMN_ALIAS),
            ),
            performanceAnalysisAggregatedUserStatisticValuesPerHourInEvaluationPeriod: $this->buildStatisticsFromResult(
                $result,
                self::TOTAL_COLUMN_ALIAS,
                $userEntitiesById,
                $userSnapshots,
                fn(array $result) => round(array_sum(array_column($result, self::TOTAL_COLUMN_ALIAS)) / count($result), PickingStatisticRounding::PRECISION),
            ),
        );
    }

    /**
     * @return array<StatisticsQueryResult>
     */
    private function fetchStatistics(PickingStatisticFilter $filter, DateTimeImmutable $fromDateTime, DateTimeImmutable $toDateTime): array
    {
        $additionalConditions = $this->buildAdditionalWhereConditions($filter);
        $filters = $filter->buildWhereClausesForTable(
            tableAlias: 'e',
            createdAtColumnName: $this->createdAtColumnName,
            userRoleTableName: $this->userRoleTableName,
            userRoleEventIdColumnName: $this->userRoleEventIdColumnName,
            fromDateTime: $fromDateTime,
            toDateTime: $toDateTime,
            additionalConditions: $additionalConditions['conditions'],
            additionalParams: $additionalConditions['params'],
            additionalTypes: $additionalConditions['types'],
        );

        $where = implode(' AND ', $filters['conditions']);
        $activeHoursFilters = $filter->buildActiveHoursFilters($fromDateTime, $toDateTime);

        $aliasTotal = self::TOTAL_COLUMN_ALIAS;
        $aliasAvgPerDay = self::AVG_PER_DAY_COLUMN_ALIAS;
        $aliasAvgPerHour = self::AVG_PER_HOUR_COLUMN_ALIAS;
        $aliasActiveDays = self::ACTIVE_DAYS_COLUMN_ALIAS;
        $aliasActiveHours = self::ACTIVE_HOURS_COLUMN_ALIAS;

        $sql = <<<SQL
            WITH per_hour AS (
                SELECT
                    e.user_reference_id,
                    e.{$this->createdAtDayColumnName} AS day,
                    e.{$this->createdAtHourColumnName} AS hour,
                    {$this->valueSelectStatement} AS in_hour
                FROM {$this->tableName} e
                WHERE {$where}
                GROUP BY
                    e.user_reference_id,
                    day,
                    hour
            ),
            per_user AS (
                SELECT
                    per_hour.user_reference_id,
                    SUM(in_hour) AS total
                FROM per_hour
                GROUP BY per_hour.user_reference_id
            ),
            active_time_per_user AS (
                SELECT
                    distinct_active_hours.user_reference_id,
                    COUNT(DISTINCT day) AS active_days,
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
            )
            SELECT
                LOWER(HEX(active_time_per_user.user_reference_id)) AS user_reference_id,
                COALESCE(per_user.total, 0) AS {$aliasTotal},
                COALESCE(per_user.total, 0) / NULLIF(COALESCE(active_time_per_user.active_days, 0), 0) AS {$aliasAvgPerDay},
                COALESCE(per_user.total, 0) / NULLIF(COALESCE(active_time_per_user.active_hours, 0), 0) AS {$aliasAvgPerHour},
                COALESCE(active_time_per_user.active_days, 0) AS {$aliasActiveDays},
                COALESCE(active_time_per_user.active_hours, 0) AS {$aliasActiveHours}
            FROM active_time_per_user
            LEFT JOIN per_user
                ON active_time_per_user.user_reference_id = per_user.user_reference_id
            ORDER BY {$aliasTotal} DESC
            SQL;

        $params = array_merge($activeHoursFilters['params'], $filters['params']);
        $types = array_merge($activeHoursFilters['types'], $filters['types']);

        return $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    /**
     * @return array{conditions: string[], params: array<string, mixed>, types: array<string, string>}
     */
    protected function buildAdditionalWhereConditions(
        PickingStatisticFilter $filter,
    ): array {
        return [
            'conditions' => [],
            'params' => [],
            'types' => [],
        ];
    }

    /**
     * @param array<StatisticsQueryResult> $result
     * @param array<string, UserEntity> $userEntitiesById
     * @param array<string, array{firstName: ?string, lastName: ?string}> $userSnapshots
     */
    private function buildStatisticsFromResult(
        array $result,
        string $valueColumn,
        array $userEntitiesById,
        array $userSnapshots,
        callable $calculateAverage,
    ): PerformanceAnalysisAggregatedUserStatisticValues {
        $statisticValues = array_column($result, $valueColumn);

        $max = 0;
        $min = 0;
        $avg = 0;
        if (count($statisticValues) > 0) {
            $max = max($statisticValues);
            $min = min($statisticValues);
            $avg = $calculateAverage($result);
        }

        $userStatisticValues = [];
        foreach ($result as $row) {
            $userEntity = $userEntitiesById[$row['user_reference_id']] ?? null;
            if ($userEntity) {
                $firstName = $userEntity->getFirstName();
                $lastName = $userEntity->getLastName();
                $existsInDatabase = true;
            } else {
                $userSnapshot = $userSnapshots[$row['user_reference_id']];
                $firstName = $userSnapshot['firstName'];
                $lastName = $userSnapshot['lastName'];
                $existsInDatabase = false;
            }

            $userStatisticValues[] = new PerformanceAnalysisUserStatisticValue(
                $row['user_reference_id'],
                StatisticValue::fromFloat((float) $row[$valueColumn]),
                $firstName,
                $lastName,
                $existsInDatabase,
            );
        }

        return new PerformanceAnalysisAggregatedUserStatisticValues(
            StatisticValue::fromFloat($avg),
            StatisticValue::fromFloat((float) $max),
            StatisticValue::fromFloat((float) $min),
            $userStatisticValues,
        );
    }

    private function getWeightedAverageCalculator(string $activeTimeColumn): callable
    {
        return function(array $result) use ($activeTimeColumn) {
            $totalActiveTime = array_sum(array_column($result, $activeTimeColumn));
            if ($totalActiveTime == 0) {
                return 0.0;
            }

            return round(
                array_sum(array_column($result, self::TOTAL_COLUMN_ALIAS)) / $totalActiveTime,
                PickingStatisticRounding::PRECISION,
            );
        };
    }
}
