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

class Weight implements JsonSerializable
{
    use PhysicalQuantity;

    private const METRIC_UNITS = [
        'mg' => 1E-6,
        'g' => 1E-3,
        'kg' => 1E0,
        't' => 1E3,
    ];
    private const IMPERIAL_UNITS = [
        'lb' => 0.45359237,
        'oz' => 0.0283495231,
    ];
    private const UNITS = [
        ...self::METRIC_UNITS,
        ...self::IMPERIAL_UNITS,
    ];
    private const BASIC_SI_UNIT = 'kg';
}
