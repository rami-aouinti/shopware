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

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\Statistic\Dto\PickingStatisticFilter;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;

class PickingStatisticPerformanceAnalysisDeliveriesCalculator extends AbstractPickingStatisticPerformanceAnalysisCalculator
{
    public function __construct(
        Connection $connection,
        EntityManager $entityManager,
        PickingStatisticUserSnapshotLoader $userSnapshotLoader,
    ) {
        parent::__construct(
            $connection,
            $entityManager,
            $userSnapshotLoader,
            tableName: 'pickware_wms_delivery_lifecycle_event',
            userRoleTableName: 'pickware_wms_delivery_lifecycle_event_user_role',
            userRoleEventIdColumnName: 'delivery_lifecycle_event_id',
            createdAtColumnName: 'event_created_at',
            createdAtDayColumnName: 'event_created_at_day',
            createdAtHourColumnName: 'event_created_at_hour',
        );
    }

    protected function buildAdditionalWhereConditions(
        PickingStatisticFilter $filter,
    ): array {
        return [
            'conditions' => ['e.event_technical_name = :eventTechnicalName'],
            'params' => ['eventTechnicalName' => DeliveryLifecycleEventType::Complete->value],
            'types' => [],
        ];
    }
}
