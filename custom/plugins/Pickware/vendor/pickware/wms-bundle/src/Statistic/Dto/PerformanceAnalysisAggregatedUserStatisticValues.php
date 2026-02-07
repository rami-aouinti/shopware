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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class PerformanceAnalysisAggregatedUserStatisticValues
{
    /**
     * @param PerformanceAnalysisUserStatisticValue[] $userStatisticValues
     */
    public function __construct(
        public StatisticValue|StatisticValueWithReference $average,
        public StatisticValue|StatisticValueWithReference $max,
        public StatisticValue|StatisticValueWithReference $min,
        public array $userStatisticValues,
    ) {}

    public function withReferenceValues(self $referenceValues): self
    {
        $userStatisticValues = [];
        foreach ($this->userStatisticValues as $userStatisticValue) {
            $referenceUserStatisticValue = array_find($referenceValues->userStatisticValues, fn(PerformanceAnalysisUserStatisticValue $referenceUserStatisticValue) => $referenceUserStatisticValue->id === $userStatisticValue->id);

            if (!$referenceUserStatisticValue) {
                $referenceUserStatisticValue = new PerformanceAnalysisUserStatisticValue(
                    $userStatisticValue->id,
                    StatisticValue::fromFloat(0),
                    $userStatisticValue->firstName,
                    $userStatisticValue->lastName,
                    $userStatisticValue->existsInDatabase,
                );
            }

            $userStatisticValues[] = $userStatisticValue->addReferenceValue($referenceUserStatisticValue);
        }

        return new self(
            StatisticValueWithReference::fromValues($this->average->value, $referenceValues->average->value),
            StatisticValueWithReference::fromValues($this->max->value, $referenceValues->max->value),
            StatisticValueWithReference::fromValues($this->min->value, $referenceValues->min->value),
            $userStatisticValues,
        );
    }
}
