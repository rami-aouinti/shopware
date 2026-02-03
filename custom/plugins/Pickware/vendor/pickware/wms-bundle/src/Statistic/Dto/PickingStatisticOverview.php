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
readonly class PickingStatisticOverview
{
    public function __construct(
        public StatisticValue|StatisticValueWithReference $picksPerHour,
        public StatisticValue|StatisticValueWithReference $totalPicks,
        public StatisticValue|StatisticValueWithReference $pickedUnits,
        public StatisticValue|StatisticValueWithReference $pickedDeliveries,
    ) {}

    public function withReferenceValues(self $referenceValues): self
    {
        return new self(
            StatisticValueWithReference::fromValues($this->picksPerHour->value, $referenceValues->picksPerHour->value),
            StatisticValueWithReference::fromValues($this->totalPicks->value, $referenceValues->totalPicks->value),
            StatisticValueWithReference::fromValues($this->pickedUnits->value, $referenceValues->pickedUnits->value),
            StatisticValueWithReference::fromValues($this->pickedDeliveries->value, $referenceValues->pickedDeliveries->value),
        );
    }
}
