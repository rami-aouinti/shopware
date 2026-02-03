<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Import;

use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizedColumnMapping;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizer;

class BinLocationImportCsvRowNormalizer extends CsvRowNormalizer
{
    public function __construct()
    {
        $mapping = new CsvRowNormalizedColumnMapping([
            'code' => [
                'code',
                'name',
            ],
            'position' => ['position'],
        ]);
        parent::__construct($mapping);
    }

    public function normalizeRow(array $row): array
    {
        $row = parent::normalizeRow($row);

        $row = $this->mapTypes($row);

        return $row;
    }

    private function mapTypes(array $row): array
    {
        if (isset($row['position']) && self::isIntegerString($row['position'])) {
            $row['position'] = (int)$row['position'];
        } elseif (isset($row['position'])) {
            $row['position'] = null;
        }

        return $row;
    }
}
