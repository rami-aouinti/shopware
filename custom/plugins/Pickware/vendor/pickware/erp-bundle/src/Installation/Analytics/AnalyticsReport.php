<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Analytics;

class AnalyticsReport
{
    private string $technicalName;
    private string $aggregationTechnicalName;

    public function __construct(string $technicalName, string $aggregationTechnicalName)
    {
        $this->technicalName = $technicalName;
        $this->aggregationTechnicalName = $aggregationTechnicalName;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getAggregationTechnicalName(): string
    {
        return $this->aggregationTechnicalName;
    }
}
