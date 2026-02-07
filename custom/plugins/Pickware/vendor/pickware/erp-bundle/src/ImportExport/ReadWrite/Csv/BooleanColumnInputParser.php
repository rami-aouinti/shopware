<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv;

class BooleanColumnInputParser
{
    public function parseBooleanColumnOfRow(array &$row, string $columnName): void
    {
        if (!isset($row[$columnName])) {
            return;
        }

        $lowercaseRowContent = mb_strtolower($row[$columnName]);
        $trueValues = [
            'yes',
            'y',
            'ja',
            'j',
        ];
        if (in_array($lowercaseRowContent, $trueValues)) {
            $row[$columnName] = 'true';
        }
        $falseValues = [
            'no',
            'n',
            'nein',
        ];
        if (in_array($lowercaseRowContent, $falseValues)) {
            $row[$columnName] = 'false';
        }
    }
}
