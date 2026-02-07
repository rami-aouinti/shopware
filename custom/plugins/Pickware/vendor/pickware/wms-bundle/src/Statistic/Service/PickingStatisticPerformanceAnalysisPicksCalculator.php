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

class PickingStatisticPerformanceAnalysisPicksCalculator extends AbstractPickingStatisticPerformanceAnalysisCalculator
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
            tableName: 'pickware_wms_pick_event',
            userRoleTableName: 'pickware_wms_pick_event_user_role',
            userRoleEventIdColumnName: 'pick_id',
            createdAtColumnName: 'pick_created_at',
            createdAtDayColumnName: 'pick_created_at_day',
            createdAtHourColumnName: 'pick_created_at_hour',
        );
    }
}
