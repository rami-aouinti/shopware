<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PhpStandardLibrary\Language;

use BackedEnum;
use Throwable;
use UnitEnum;

function convertExceptionToArray(Throwable $exception): array
{
    $details = [
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'trace' => escapeStringsInStacktrace($exception->getTrace()),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'class' => $exception::class,
    ];

    $previous = $exception->getPrevious();
    if ($previous) {
        $details['previous'] = convertExceptionToArray($previous);
    }

    return $details;
}

function escapeStringsInStacktrace(array $array): array
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = escapeStringsInStacktrace($value);
        }

        if (is_string($value)) {
            if (!ctype_print($value) && mb_strlen($value) === 16) {
                $array[$key] = sprintf('ATTENTION: Converted binary string: %s', bin2hex($value));
            } elseif (!mb_detect_encoding($value, mb_detect_order(), true)) {
                $array[$key] = utf8_encode($value);
            }
        }

        if ($value instanceof UnitEnum && !($value instanceof BackedEnum)) {
            $array[$key] = $value->name;
        }
    }

    return $array;
}
