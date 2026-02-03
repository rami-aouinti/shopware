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

class Time implements JsonSerializable
{
    use PhysicalQuantity;

    private const UNITS = [
        'ps' => 1E-9,
        'ns' => 1E-9,
        'μs' => 1E-6,
        'ms' => 1E-3,
        's' => 1E0,
        'min' => 60,
        'h' => 60 * 60,
        'd' => 24 * 60 * 60,
        'a' => 365 * 24 * 60 * 60,
    ];
    private const BASIC_SI_UNIT = 's';
}
