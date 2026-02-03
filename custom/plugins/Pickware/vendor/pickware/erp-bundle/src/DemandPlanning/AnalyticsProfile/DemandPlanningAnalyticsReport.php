<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile;

use Pickware\PickwareErpStarter\Installation\Analytics\AnalyticsReport;

class DemandPlanningAnalyticsReport extends AnalyticsReport
{
    public const TECHNICAL_NAME = 'demand_planning';

    public function __construct()
    {
        parent::__construct(self::TECHNICAL_NAME, DemandPlanningAnalyticsAggregation::TECHNICAL_NAME);
    }
}
