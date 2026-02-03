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
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class StatisticValue
{
    private function __construct(
        public float $value,
    ) {}

    public static function fromFloat(float $value): self
    {
        return new self(round($value, PickingStatisticRounding::PRECISION));
    }
}
