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

class AnalyticsReportEntity extends Entity
{
    protected string $technicalName;
    protected string $aggregationTechnicalName;
    protected ?AnalyticsAggregationEntity $aggregation = null;
    protected ?AnalyticsReportConfigDefinition $reportConfigs = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getAggregationTechnicalName(): string
    {
        return $this->aggregationTechnicalName;
    }

    public function setAggregationTechnicalName(string $aggregationTechnicalName): void
    {
        if ($this->aggregation && $this->aggregation->getTechnicalName() !== $aggregationTechnicalName) {
            $this->aggregation = null;
        }

        $this->aggregationTechnicalName = $aggregationTechnicalName;
    }

    public function getAggregation(): AnalyticsAggregationEntity
    {
        if (!$this->aggregation) {
            throw new AssociationNotLoadedException('aggregation', $this);
        }

        return $this->aggregation;
    }

    public function setAggregation(AnalyticsAggregationEntity $aggregation): void
    {
        $this->aggregation = $aggregation;
    }

    public function getReportConfigs(): AnalyticsReportConfigDefinition
    {
        if (!$this->reportConfigs) {
            throw new AssociationNotLoadedException('reportConfigs', $this);
        }

        return $this->reportConfigs;
    }

    public function setReportConfigs(AnalyticsReportConfigDefinition $reportConfigs): void
    {
        $this->reportConfigs = $reportConfigs;
    }
}
