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

use JsonSerializable;
use Pickware\PickwareErpStarter\Analytics\AnalyticsReportConfigFactory;

class DemandPlanningAnalyticsReportConfigFactory implements AnalyticsReportConfigFactory
{
    public function createCalculatorConfigFromArray(array $serialized): JsonSerializable
    {
        return new DemandPlanningAnalyticsCalculatorConfig();
    }

    public function createDefaultCalculatorConfig(): JsonSerializable
    {
        return new DemandPlanningAnalyticsCalculatorConfig();
    }

    public function getReportTechnicalName(): string
    {
        return DemandPlanningAnalyticsReport::TECHNICAL_NAME;
    }
}
