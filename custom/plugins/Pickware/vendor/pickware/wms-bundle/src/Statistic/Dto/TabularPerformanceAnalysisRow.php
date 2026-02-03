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
readonly class TabularPerformanceAnalysisRow
{
    public function __construct(
        public string $id,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $username,
        public StatisticValue $activeHours,
        public StatisticValue $activeDays,
        public StatisticValue $picks,
        public StatisticValue $pickedUnits,
        public StatisticValue $pickedOrders,
        public StatisticValue $shippedDeliveries,
        public StatisticValue $picksPerHour,
        public StatisticValue $pickedUnitsPerHour,
        public StatisticValue $pickedOrdersPerHour,
        public StatisticValue $shippedDeliveriesPerHour,
        public StatisticValue $picksPerDay,
        public StatisticValue $pickedUnitsPerDay,
        public StatisticValue $pickedOrdersPerDay,
        public StatisticValue $shippedDeliveriesPerDay,
        public StatisticValue $deferredPickingProcesses,
        public StatisticValue $cancelledPickingProcesses,
        public bool $existsInDatabase,
    ) {}
}
