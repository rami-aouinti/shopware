<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Dto;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class PickingStatisticFilter
{
    /**
     * @param string[] $warehouseIds
     * @param string[] $userIds
     * @param string[] $userRoleIds
     * @param string[] $pickingProfileIds
     * @param string[] $pickingModes
     */
    public function __construct(
        public TimePeriod $timePeriod,
        public ?TimePeriod $referencePeriod,
        public array $warehouseIds,
        public array $userIds,
        public array $userRoleIds,
        public array $pickingProfileIds,
        public array $pickingModes,
        public DateTimeZone $timeZone,
    ) {}

    /**
     * @param array{
     *     evaluationPeriod: array{fromDateTime: string, toDateTime: string},
     *     referencePeriod?: array{fromDateTime: string, toDateTime: string}|null,
     *     warehouseIds: string[],
     *     userIds: string[],
     *     userRoleIds: string[],
     *     pickingProfileIds: string[],
     *     pickingModes: string[],
     *     timeZone?: string,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            TimePeriod::fromArray($data['evaluationPeriod']),
            isset($data['referencePeriod']) ? TimePeriod::fromArray($data['referencePeriod']) : null,
            $data['warehouseIds'],
            $data['userIds'],
            $data['userRoleIds'],
            $data['pickingProfileIds'],
            $data['pickingModes'],
            new DateTimeZone($data['timeZone'] ?? 'UTC'),
        );
    }

    /**
     * @param array<string> $additionalConditions
     * @param array<string, mixed> $additionalParams
     * @param array<string, string|ArrayParameterType> $additionalTypes
     * @return array{conditions: string[], params: array<string, mixed>, types: array<string, string|ArrayParameterType>}
     */
    public function buildWhereClausesForTable(
        string $tableAlias,
        string $createdAtColumnName,
        string $userRoleTableName,
        string $userRoleEventIdColumnName,
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
        array $additionalConditions,
        array $additionalParams,
        array $additionalTypes,
    ): array {
        $conditions = [
            "{$tableAlias}.{$createdAtColumnName} BETWEEN :fromDateTime AND :toDateTime",
            ...$additionalConditions,
        ];
        $params = array_merge(
            [
                'fromDateTime' => $fromDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'toDateTime' => $toDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            $additionalParams,
        );
        $types = $additionalTypes;

        if (count($this->warehouseIds) > 0) {
            $conditions[] = "{$tableAlias}.warehouse_reference_id IN (:warehouseIds)";
            $params['warehouseIds'] = Uuid::fromHexToBytesList($this->warehouseIds);
            $types['warehouseIds'] = ArrayParameterType::BINARY;
        }

        if (count($this->userIds) > 0) {
            $conditions[] = "{$tableAlias}.user_reference_id IN (:userIds)";
            $params['userIds'] = Uuid::fromHexToBytesList($this->userIds);
            $types['userIds'] = ArrayParameterType::BINARY;
        }

        if (count($this->pickingProfileIds) > 0) {
            $conditions[] = "{$tableAlias}.picking_profile_reference_id IN (:pickingProfileIds)";
            $params['pickingProfileIds'] = Uuid::fromHexToBytesList($this->pickingProfileIds);
            $types['pickingProfileIds'] = ArrayParameterType::BINARY;
        }

        if (count($this->pickingModes) > 0) {
            $conditions[] = "{$tableAlias}.picking_mode IN (:pickingModes)";
            $params['pickingModes'] = $this->pickingModes;
            $types['pickingModes'] = ArrayParameterType::STRING;
        }

        if (count($this->userRoleIds) > 0) {
            $conditions[] = sprintf(
                'EXISTS (
                    SELECT 1 FROM %s ur
                    WHERE ur.%s = %s.id
                      AND ur.user_role_reference_id IN (:userRoleIds)
                )',
                $userRoleTableName,
                $userRoleEventIdColumnName,
                $tableAlias,
            );
            $params['userRoleIds'] = Uuid::fromHexToBytesList($this->userRoleIds);
            $types['userRoleIds'] = ArrayParameterType::BINARY;
        }

        return [
            'conditions' => $conditions,
            'params' => $params,
            'types' => $types,
        ];
    }

    /**
     * Builds WHERE clause filters for all three event types (picks, deliveries, picking processes)
     * used to calculate active hours across all user activities.
     *
     * @return array{
     *     pickWhere: string,
     *     deliveryWhere: string,
     *     processWhere: string,
     *     params: array<string, mixed>,
     *     types: array<string, ArrayParameterType|string>
     * }
     */
    public function buildActiveHoursFilters(
        DateTimeImmutable $fromDateTime,
        DateTimeImmutable $toDateTime,
    ): array {
        $pickFilters = $this->buildWhereClausesForTable(
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

        $deliveryFilters = $this->buildWhereClausesForTable(
            tableAlias: 'de',
            createdAtColumnName: 'event_created_at',
            userRoleTableName: 'pickware_wms_delivery_lifecycle_event_user_role',
            userRoleEventIdColumnName: 'delivery_lifecycle_event_id',
            fromDateTime: $fromDateTime,
            toDateTime: $toDateTime,
            additionalConditions: [],
            additionalParams: [],
            additionalTypes: [],
        );

        $processFilters = $this->buildWhereClausesForTable(
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

        return [
            'pickWhere' => implode(' AND ', $pickFilters['conditions']),
            'deliveryWhere' => implode(' AND ', $deliveryFilters['conditions']),
            'processWhere' => implode(' AND ', $processFilters['conditions']),
            'params' => array_merge($pickFilters['params'], $deliveryFilters['params'], $processFilters['params']),
            'types' => array_merge($pickFilters['types'], $deliveryFilters['types'], $processFilters['types']),
        ];
    }
}
