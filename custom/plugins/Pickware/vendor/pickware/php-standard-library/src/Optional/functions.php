<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PhpStandardLibrary\Optional;

/**
 * @template Value
 * @template Return
 * @param Value|null $value
 * @param callable(Value):Return $callback
 * @return ($value is null ? null : Return)
 */
function doIf(mixed &$value, callable $callback): mixed
{
    if (isset($value)) {
        return $callback($value);
    }

    return null;
}
