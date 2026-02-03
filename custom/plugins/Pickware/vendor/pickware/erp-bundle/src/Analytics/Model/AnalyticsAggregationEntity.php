<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class AnalyticsAggregationEntity extends Entity
{
    protected string $technicalName;
    protected ?AnalyticsReportCollection $reports = null;
    protected ?AnalyticsAggregationSessionCollection $aggregationSessions = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getReports(): AnalyticsReportCollection
    {
        if (!$this->reports) {
            throw new AssociationNotLoadedException('reports', $this);
        }

        return $this->reports;
    }

    public function setReports(AnalyticsReportCollection $reports): void
    {
        $this->reports = $reports;
    }

    public function getAggregationSessions(): AnalyticsAggregationSessionCollection
    {
        if (!$this->aggregationSessions) {
            throw new AssociationNotLoadedException('aggregationSessions', $this);
        }

        return $this->aggregationSessions;
    }

    public function setAggregationSessions(AnalyticsAggregationSessionCollection $analyticsSessions): void
    {
        $this->aggregationSessions = $analyticsSessions;
    }
}
