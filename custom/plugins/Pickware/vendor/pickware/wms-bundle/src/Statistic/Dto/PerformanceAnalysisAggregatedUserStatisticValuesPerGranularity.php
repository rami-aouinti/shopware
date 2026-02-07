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

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class PerformanceAnalysisAggregatedUserStatisticValuesPerGranularity implements JsonSerializable
{
    public function __construct(
        public PerformanceAnalysisAggregatedUserStatisticValues $performanceAnalysisAggregatedUserStatisticValuesPerHour,
        public PerformanceAnalysisAggregatedUserStatisticValues $performanceAnalysisAggregatedUserStatisticValuesPerDay,
        public PerformanceAnalysisAggregatedUserStatisticValues $performanceAnalysisAggregatedUserStatisticValuesPerHourInEvaluationPeriod,
    ) {}

    /**
     * @return array<value-of<PerformanceAnalysisGranularity>, PerformanceAnalysisAggregatedUserStatisticValues>
     */
    public function jsonSerialize(): array
    {
        return [
            PerformanceAnalysisGranularity::PerHour->value => $this->performanceAnalysisAggregatedUserStatisticValuesPerHour,
            PerformanceAnalysisGranularity::PerDay->value => $this->performanceAnalysisAggregatedUserStatisticValuesPerDay,
            PerformanceAnalysisGranularity::InEvaluationPeriod->value => $this->performanceAnalysisAggregatedUserStatisticValuesPerHourInEvaluationPeriod,
        ];
    }

    public function withReferenceValues(PerformanceAnalysisAggregatedUserStatisticValuesPerGranularity $referenceValues): self
    {
        return new self(
            $this->performanceAnalysisAggregatedUserStatisticValuesPerHour->withReferenceValues($referenceValues->performanceAnalysisAggregatedUserStatisticValuesPerHour),
            $this->performanceAnalysisAggregatedUserStatisticValuesPerDay->withReferenceValues($referenceValues->performanceAnalysisAggregatedUserStatisticValuesPerDay),
            $this->performanceAnalysisAggregatedUserStatisticValuesPerHourInEvaluationPeriod->withReferenceValues($referenceValues->performanceAnalysisAggregatedUserStatisticValuesPerHourInEvaluationPeriod),
        );
    }
}
