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
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\Statistic\Dto\PickingStatisticFilter;
use Pickware\PickwareWms\Statistic\Dto\StatisticValue;
use Pickware\PickwareWms\Statistic\Dto\TabularPerformanceAnalysis;
use Pickware\PickwareWms\Statistic\Dto\TabularPerformanceAnalysisRow;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

class PickingStatisticTabularPerformanceAnalysisCalculator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly PickingStatisticUserSnapshotLoader $userSnapshotLoader,
    ) {}

    public function calculateTabularPerformanceAnalysis(
        PickingStatisticFilter $filter,
        Context $context,
    ): TabularPerformanceAnalysis {
        $fromDateTime = $filter->timePeriod->fromDateTime;
        $toDateTime = $filter->timePeriod->toDateTime;

        $pickStats = $this->fetchPickStatistics($filter, $fromDateTime, $toDateTime);
        $deliveryStats = $this->fetchDeliveryStatistics($filter, $fromDateTime, $toDateTime);
        $activeTimeStats = $this->fetchActiveTimeStatistics($filter, $fromDateTime, $toDateTime);
        $pickingProcessStats = $this->fetchPickingProcessStatistics($filter, $fromDateTime, $toDateTime);

        $userIds = array_keys(
            $pickStats + $deliveryStats + $activeTimeStats + $pickingProcessStats,
        );

        /** @var EntityCollection<UserEntity> $userEntities */
        $userEntities = $this->entityManager->findBy(
            UserDefinition::class,
            ['id' => $userIds],
            $context,
        );

        $userEntitiesById = [];
        foreach ($userEntities as $userEntity) {
            $userEntitiesById[$userEntity->getId()] = $userEntity;
        }

        $missingUserIds = array_values(array_diff($userIds, array_keys($userEntitiesById)));
        $userSnapshots = $missingUserIds === [] ? [] : $this->userSnapshotLoader->loadLatestUserSnapshots($missingUserIds);

        $rows = [];
        foreach ($userIds as $userId) {
            $userEntity = $userEntitiesById[$userId] ?? null;
            $userSnapshot = $userSnapshots[$userId] ?? [
                'firstName' => null,
                'lastName' => null,
                'username' => null,
            ];

            $activeHours = (int) ($activeTimeStats[$userId]['activeHours'] ?? 0);
            $activeDays = (int) ($activeTimeStats[$userId]['activeDays'] ?? 0);

            $picks = (int) ($pickStats[$userId]['picks'] ?? 0);
            $pickedUnits = (float) ($pickStats[$userId]['pickedUnits'] ?? 0.0);
            $pickedOrders = (int) ($deliveryStats[$userId]['pickedOrders'] ?? 0);
            $shippedDeliveries = (int) ($deliveryStats[$userId]['shippedDeliveries'] ?? 0);

            $deferredPickingProcesses = (int) ($pickingProcessStats[$userId]['deferredPickingProcesses'] ?? 0);
            $cancelledPickingProcesses = (int) ($pickingProcessStats[$userId]['cancelledPickingProcesses'] ?? 0);

            $rows[] = new TabularPerformanceAnalysisRow(
                $userId,
                $userEntity?->getFirstName() ?? $userSnapshot['firstName'],
                $userEntity?->getLastName() ?? $userSnapshot['lastName'],
                $userEntity?->getUsername() ?? ($userSnapshot['username']),
                StatisticValue::fromFloat($activeHours),
                StatisticValue::fromFloat($activeDays),
                StatisticValue::fromFloat($picks),
                StatisticValue::fromFloat($pickedUnits),
                StatisticValue::fromFloat($pickedOrders),
                StatisticValue::fromFloat($shippedDeliveries),
                $this->calculateRatePerActiveTime($picks, $activeHours),
                $this->calculateRatePerActiveTime($pickedUnits, $activeHours),
                $this->calculateRatePerActiveTime($pickedOrders, $activeHours),
                $this->calculateRatePerActiveTime($shippedDeliveries, $activeHours),
                $this->calculateRatePerActiveTime($picks, $activeDays),
                $this->calculateRatePerActiveTime($pickedUnits, $activeDays),
                $this->calculateRatePerActiveTime($pickedOrders, $activeDays),
                $this->calculateRatePerActiveTime($shippedDeliveries, $activeDays),
                StatisticValue::fromFloat($deferredPickingProcesses),
                StatisticValue::fromFloat($cancelledPickingProcesses),
                $userEntity !== null,
            );
        }

        usort(
            $rows,
            fn(TabularPerformanceAnalysisRow $left, TabularPerformanceAnalysisRow $right) => $right->picksPerHour->value <=> $left->picksPerHour->value,
        );

        return new TabularPerformanceAnalysis($rows);
    }

    private function calculateRatePerActiveTime(float|int $value, int $activeTime): StatisticValue
    {
        if ($activeTime <= 0) {
            return StatisticValue::fromFloat(0.0);
        }

        return StatisticValue::fromFloat($value / $activeTime);
    }

    /**
     * @return array<string, array{picks: int, pickedUnits: float}>
     */
    private function fetchPickStatistics(
        PickingStatisticFilter $filter,
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
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
        $sql = <<<SQL
            SELECT
                LOWER(HEX(pe.user_reference_id)) AS user_reference_id,
                COUNT(*) AS picks,
                SUM(pe.picked_quantity) AS picked_units
            FROM pickware_wms_pick_event pe
            WHERE {$where}
              AND pe.user_reference_id IS NOT NULL
            GROUP BY pe.user_reference_id
            SQL;

        $rows = $this->connection->executeQuery($sql, $filters['params'], $filters['types'])->fetchAllAssociative();

        $statistics = [];
        foreach ($rows as $row) {
            $statistics[$row['user_reference_id']] = [
                'picks' => (int) $row['picks'],
                'pickedUnits' => (float) $row['picked_units'],
            ];
        }

        return $statistics;
    }

    /**
     * @return array<string, array{pickedOrders: int, shippedDeliveries: int}>
     */
    private function fetchDeliveryStatistics(
        PickingStatisticFilter $filter,
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
    ): array {
        $filters = $filter->buildWhereClausesForTable(
            tableAlias: 'e',
            createdAtColumnName: 'event_created_at',
            userRoleTableName: 'pickware_wms_delivery_lifecycle_event_user_role',
            userRoleEventIdColumnName: 'delivery_lifecycle_event_id',
            fromDateTime: $fromDateTime,
            toDateTime: $toDateTime,
            additionalConditions: ['e.event_technical_name IN (:deliveryEventNames)'],
            additionalParams: [
                'deliveryEventNames' => [
                    DeliveryLifecycleEventType::Complete->value,
                    DeliveryLifecycleEventType::Ship->value,
                ],
                'completeEventName' => DeliveryLifecycleEventType::Complete->value,
                'shipEventName' => DeliveryLifecycleEventType::Ship->value,
            ],
            additionalTypes: [
                'deliveryEventNames' => ArrayParameterType::STRING,
            ],
        );

        $where = implode(' AND ', $filters['conditions']);
        $sql = <<<SQL
            SELECT
                LOWER(HEX(e.user_reference_id)) AS user_reference_id,
                COUNT(CASE WHEN e.event_technical_name = :completeEventName THEN 1 END) AS picked_orders,
                COUNT(CASE WHEN e.event_technical_name = :shipEventName THEN 1 END) AS shipped_deliveries
            FROM pickware_wms_delivery_lifecycle_event e
            WHERE {$where}
              AND e.user_reference_id IS NOT NULL
            GROUP BY e.user_reference_id
            SQL;

        $rows = $this->connection->executeQuery($sql, $filters['params'], $filters['types'])->fetchAllAssociative();

        $statistics = [];
        foreach ($rows as $row) {
            $statistics[$row['user_reference_id']] = [
                'pickedOrders' => (int) $row['picked_orders'],
                'shippedDeliveries' => (int) $row['shipped_deliveries'],
            ];
        }

        return $statistics;
    }

    /**
     * @return array<string, array{activeDays: int, activeHours: int}>
     */
    private function fetchActiveTimeStatistics(
        PickingStatisticFilter $filter,
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
    ): array {
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
            additionalConditions: ['e.event_technical_name = :deliveryEventTechnicalName'],
            additionalParams: ['deliveryEventTechnicalName' => DeliveryLifecycleEventType::Complete->value],
            additionalTypes: [],
        );

        $processFilters = $filter->buildWhereClausesForTable(
            tableAlias: 'pp',
            createdAtColumnName: 'event_created_at',
            userRoleTableName: 'pickware_wms_picking_process_lifecycle_event_user_role',
            userRoleEventIdColumnName: 'picking_process_lifecycle_event_id',
            fromDateTime: $fromDateTime,
            toDateTime: $toDateTime,
            additionalConditions: [],
            additionalParams: [],
            additionalTypes: [],
        );

        $pickWhere = implode(' AND ', $pickFilters['conditions']);
        $deliveryWhere = implode(' AND ', $deliveryFilters['conditions']);
        $processWhere = implode(' AND ', $processFilters['conditions']);

        $sql = <<<SQL
            WITH per_hour AS (
                SELECT
                    pe.user_reference_id,
                    pe.pick_created_at_day AS day,
                    pe.pick_created_at_hour AS hour
                FROM pickware_wms_pick_event pe
                WHERE {$pickWhere}
                  AND pe.user_reference_id IS NOT NULL
                GROUP BY
                    pe.user_reference_id,
                    pe.pick_created_at_day,
                    pe.pick_created_at_hour

                UNION

                SELECT
                    e.user_reference_id,
                    e.event_created_at_day AS day,
                    e.event_created_at_hour AS hour
                FROM pickware_wms_delivery_lifecycle_event e
                WHERE {$deliveryWhere}
                  AND e.user_reference_id IS NOT NULL
                GROUP BY
                    e.user_reference_id,
                    e.event_created_at_day,
                    e.event_created_at_hour

                UNION

                SELECT
                    pp.user_reference_id,
                    pp.event_created_at_day AS day,
                    pp.event_created_at_hour AS hour
                FROM pickware_wms_picking_process_lifecycle_event pp
                WHERE {$processWhere}
                  AND pp.user_reference_id IS NOT NULL
                GROUP BY
                    pp.user_reference_id,
                    pp.event_created_at_day,
                    pp.event_created_at_hour
            )
            SELECT
                LOWER(HEX(user_reference_id)) AS user_reference_id,
                COUNT(DISTINCT day) AS active_days,
                COUNT(*) AS active_hours
            FROM per_hour
            GROUP BY user_reference_id
            SQL;

        $params = array_merge($pickFilters['params'], $deliveryFilters['params'], $processFilters['params']);
        $types = array_merge($pickFilters['types'], $deliveryFilters['types'], $processFilters['types']);
        $rows = $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $statistics = [];
        foreach ($rows as $row) {
            $statistics[$row['user_reference_id']] = [
                'activeDays' => (int) $row['active_days'],
                'activeHours' => (int) $row['active_hours'],
            ];
        }

        return $statistics;
    }

    /**
     * @return array<string, array{deferredPickingProcesses: int, cancelledPickingProcesses: int}>
     */
    private function fetchPickingProcessStatistics(
        PickingStatisticFilter $filter,
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
    ): array {
        $filters = $filter->buildWhereClausesForTable(
            tableAlias: 'e',
            createdAtColumnName: 'event_created_at',
            userRoleTableName: 'pickware_wms_picking_process_lifecycle_event_user_role',
            userRoleEventIdColumnName: 'picking_process_lifecycle_event_id',
            fromDateTime: $fromDateTime,
            toDateTime: $toDateTime,
            additionalConditions: ['e.event_technical_name IN (:pickingProcessEventNames)'],
            additionalParams: [
                'pickingProcessEventNames' => [
                    PickingProcessLifecycleEventType::Defer->value,
                    PickingProcessLifecycleEventType::Cancel->value,
                ],
                'deferEventName' => PickingProcessLifecycleEventType::Defer->value,
                'cancelEventName' => PickingProcessLifecycleEventType::Cancel->value,
            ],
            additionalTypes: [
                'pickingProcessEventNames' => ArrayParameterType::STRING,
            ],
        );

        $where = implode(' AND ', $filters['conditions']);
        $sql = <<<SQL
            SELECT
                LOWER(HEX(e.user_reference_id)) AS user_reference_id,
                COUNT(DISTINCT CASE WHEN e.event_technical_name = :deferEventName THEN e.picking_process_reference_id END) AS deferred_orders,
                COUNT(DISTINCT CASE WHEN e.event_technical_name = :cancelEventName THEN e.picking_process_reference_id END) AS cancelled_orders
            FROM pickware_wms_picking_process_lifecycle_event e
            WHERE {$where}
              AND e.user_reference_id IS NOT NULL
            GROUP BY e.user_reference_id
            SQL;

        $rows = $this->connection->executeQuery($sql, $filters['params'], $filters['types'])->fetchAllAssociative();

        $statistics = [];
        foreach ($rows as $row) {
            $statistics[$row['user_reference_id']] = [
                'deferredPickingProcesses' => (int) $row['deferred_orders'],
                'cancelledPickingProcesses' => (int) $row['cancelled_orders'],
            ];
        }

        return $statistics;
    }
}
