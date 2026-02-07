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
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AnalyticsReportConfigEntity extends Entity
{
    use EntityIdTrait;

    protected string $reportTechnicalName;
    protected ?AnalyticsReportEntity $report = null;
    protected string $aggregationSessionId;
    protected ?AnalyticsAggregationSessionEntity $aggregationSession = null;
    protected ?array $listQuery;
    protected array $calculatorConfig;

    public function getReportTechnicalName(): string
    {
        return $this->reportTechnicalName;
    }

    public function setReportTechnicalName(string $reportTechnicalName): void
    {
        if ($this->report && $this->report->getTechnicalName() !== $reportTechnicalName) {
            $this->report = null;
        }

        $this->reportTechnicalName = $reportTechnicalName;
    }

    public function getReport(): AnalyticsReportEntity
    {
        if (!$this->report) {
            throw new AssociationNotLoadedException('report', $this);
        }

        return $this->report;
    }

    public function setReport(AnalyticsReportEntity $report): void
    {
        $this->report = $report;
    }

    public function getAggregationSessionId(): string
    {
        return $this->aggregationSessionId;
    }

    public function setAggregationSessionId(string $aggregationSessionId): void
    {
        if ($this->aggregationSession && $this->aggregationSession->getId() !== $aggregationSessionId) {
            $this->aggregationSession = null;
        }

        $this->aggregationSessionId = $aggregationSessionId;
    }

    public function getAggregationSession(): AnalyticsAggregationSessionEntity
    {
        if (!$this->aggregationSession) {
            throw new AssociationNotLoadedException('aggregationSession', $this);
        }

        return $this->aggregationSession;
    }

    public function setAggregationSession(AnalyticsAggregationSessionEntity $aggregationSession): void
    {
        $this->aggregationSession = $aggregationSession;
    }

    public function getListQuery(): ?array
    {
        return $this->listQuery;
    }

    public function setListQuery(?array $listQuery): void
    {
        $this->listQuery = $listQuery;
    }

    public function getCalculatorConfig(): array
    {
        return $this->calculatorConfig;
    }

    public function setCalculatorConfig(array $calculatorConfig): void
    {
        $this->calculatorConfig = $calculatorConfig;
    }
}
