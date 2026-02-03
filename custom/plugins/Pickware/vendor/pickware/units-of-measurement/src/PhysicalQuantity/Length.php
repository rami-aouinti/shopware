<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UnitsOfMeasurement\PhysicalQuantity;

use JsonSerializable;

class Length implements JsonSerializable
{
    use PhysicalQuantity;

    private const METRIC_UNITS = [
        'mm' => 1E-3, // 1 mm = 1E-3 m
        'cm' => 1E-2,
        'dm' => 1E-1,
        'm' => 1E0,
        'km' => 1E3,
    ];
    private const IMPERIAL_UNITS = [
        'in' => 0.0254,
    ];
    private const UNITS = [
        ...self::METRIC_UNITS,
        ...self::IMPERIAL_UNITS,
    ];
    private const BASIC_SI_UNIT = 'm';
}
