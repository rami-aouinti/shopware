<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PhpStandardLibrary\Json;

use InvalidArgumentException;
use JsonException;

// phpcs:disable ShopwarePlugins.Functions.NativeJsonMethods
class Json
{
    /**
     * @throws JsonException
     * @param int<1, max> $depth
     */
    public static function stringify(mixed $value, int $flags = 0, int $depth = 512): string
    {
        if (($flags & JSON_THROW_ON_ERROR) !== 0) {
            throw new InvalidArgumentException('JSON_THROW_ON_ERROR flag should not be passed as this method always throws on error');
        }

        return json_encode($value, flags: JSON_THROW_ON_ERROR | $flags, depth: $depth);
    }

    /**
     * @param int<1, max> $depth
     * @throws JsonException
     */
    public static function decodeToArray(string $json, int $flags = 0, int $depth = 512): mixed
    {
        if (($flags & JSON_THROW_ON_ERROR) !== 0) {
            throw new InvalidArgumentException('JSON_THROW_ON_ERROR flag should not be passed as this method always throws on error');
        }

        return json_decode($json, associative: true, depth: $depth, flags: JSON_THROW_ON_ERROR | $flags);
    }

    /**
     * @param int<1, max> $depth
     * @throws JsonException
     */
    public static function decodeToObject(string $json, int $flags = 0, int $depth = 512): mixed
    {
        if (($flags & JSON_THROW_ON_ERROR) !== 0) {
            throw new InvalidArgumentException('JSON_THROW_ON_ERROR flag should not be passed as this method always throws on error');
        }

        return json_decode($json, associative: false, depth: $depth, flags: JSON_THROW_ON_ERROR | $flags);
    }
}
