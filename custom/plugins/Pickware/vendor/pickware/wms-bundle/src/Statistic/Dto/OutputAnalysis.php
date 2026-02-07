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
readonly class OutputAnalysis
{
    /**
     * @param array<int, OutputAnalysisData> $byWeekday
     * @param array<int, OutputAnalysisData> $byHour
     */
    private function __construct(
        public array $byWeekday,
        public array $byHour,
    ) {}

    /**
     * @param array<int, OutputAnalysisData> $byWeekday
     * @param array<int, OutputAnalysisData> $byHour
     */
    public static function fromData(array $byWeekday, array $byHour): self
    {
        $completeWeekdayData = [];
        foreach (range(0, 6) as $weekday) {
            if (isset($byWeekday[$weekday])) {
                $completeWeekdayData[$weekday] = $byWeekday[$weekday];

                continue;
            }
            $completeWeekdayData[$weekday] = new OutputAnalysisData(
                StatisticValue::fromFloat(0.0),
                StatisticValue::fromFloat(0.0),
                StatisticValue::fromFloat(0.0),
                StatisticValue::fromFloat(0.0),
            );
        }

        $completeHourData = [];
        foreach (range(0, 23) as $hour) {
            if (isset($byHour[$hour])) {
                $completeHourData[$hour] = $byHour[$hour];

                continue;
            }
            $completeHourData[$hour] = new OutputAnalysisData(
                StatisticValue::fromFloat(0.0),
                StatisticValue::fromFloat(0.0),
                StatisticValue::fromFloat(0.0),
                StatisticValue::fromFloat(0.0),
            );
        }

        return new self(
            $completeWeekdayData,
            $completeHourData,
        );
    }
}
