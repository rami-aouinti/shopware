<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

class MemoryUtilsService
{
    /**
     * @return string|false the value of memory_limit as a string on success, or
     * an empty string on failure or for null values.
     */
    public function getMemoryLimit()
    {
        return ini_get('memory_limit');
    }

    public function hasEnoughMemory(int $required, int $actual): bool
    {
        if ($required === 0 || $actual === -1) {
            return true;
        }

        return $actual >= $required;
    }

    /**
     * Returns the given memory string as byte
     * e.g. 512M = 536870912
     * See: https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
     *
     * @param bool|string|int $byteString
     */
    public function parseMemoryString($byteString): int
    {
        if (is_bool($byteString) || $byteString === '' || $byteString < -1) {
            return 0;
        }

        $byteString = (string) $byteString;

        if ($byteString === '-1') {
            return -1;
        }

        $byteString = mb_strtolower($byteString);
        $max = mb_strtolower(ltrim($byteString, '+'));
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (mb_substr($byteString, -1)) {
            case 't':
                $max *= 1024;
                // no break
            case 'g':
                $max *= 1024;
                // no break
            case 'm':
                $max *= 1024;
                // no break
            case 'k':
                $max *= 1024;
        }

        return $max;
    }
}
