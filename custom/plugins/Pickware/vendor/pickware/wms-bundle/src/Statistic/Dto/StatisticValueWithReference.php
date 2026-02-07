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

use Pickware\PickwareWms\Statistic\PickingStatisticRounding;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class StatisticValueWithReference
{
    private function __construct(
        public float $value,
        public float $referenceValue,
        public float $changeInPercent,
    ) {}

    public static function fromValues(float $value, float $referenceValue): self
    {
        $value = round($value, PickingStatisticRounding::PRECISION);
        $referenceValue = round($referenceValue, PickingStatisticRounding::PRECISION);

        if (FloatComparator::equals($referenceValue, 0.0)) {
            return new self($value, $referenceValue, 0.0);
        }

        return new self($value, $referenceValue, round(($value - $referenceValue) / $referenceValue * 100, PickingStatisticRounding::PRECISION));
    }
}
