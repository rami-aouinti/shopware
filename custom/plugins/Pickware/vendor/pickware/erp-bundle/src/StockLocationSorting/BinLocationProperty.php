<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockLocationSorting;

use Pickware\PickwareErpStarter\StockApi\StockLocationConfiguration;

enum BinLocationProperty
{
    case Position;
    case Code;

    public function getPropertyValue(StockLocationConfiguration $config): mixed
    {
        return match ($this) {
            self::Position => $config->getPosition(),
            self::Code => $config->getCode(),
        };
    }

    public function compare(mixed $lhs, mixed $rhs): int
    {
        if ($lhs === $rhs) {
            return 0;
        }

        // If a value is null, it is sorted to the end.
        if ($lhs === null || $rhs === null) {
            return $lhs === null ? 1 : -1;
        }

        return match ($this) {
            self::Position => $lhs <=> $rhs,
            self::Code => strnatcasecmp($lhs, $rhs),
        };
    }
}
