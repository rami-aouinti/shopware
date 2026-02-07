<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PhpStandardLibrary\Range;

use InvalidArgumentException;

/**
 * @return int[]
 */
function safeRange(int $start, int $endInclusive, int $step): array
{
    if ($step <= 0) {
        throw new InvalidArgumentException('step must be greater than 0');
    }

    if ($endInclusive < $start) {
        return [];
    }

    if ($endInclusive - $start < $step) {
        return [$start];
    }

    return range($start, $endInclusive, $step);
}
