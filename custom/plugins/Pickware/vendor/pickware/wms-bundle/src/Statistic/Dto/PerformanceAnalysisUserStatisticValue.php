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
readonly class PerformanceAnalysisUserStatisticValue
{
    public function __construct(
        public string $id,
        public StatisticValue|StatisticValueWithReference $statisticValue,
        public string $firstName,
        public string $lastName,
        public bool $existsInDatabase,
    ) {}

    public function addReferenceValue(self $referenceValue): self
    {
        return new self(
            $this->id,
            StatisticValueWithReference::fromValues($this->statisticValue->value, $referenceValue->statisticValue->value),
            $this->firstName,
            $this->lastName,
            $this->existsInDatabase,
        );
    }
}
