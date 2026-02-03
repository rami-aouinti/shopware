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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CsvRowNormalizedColumnMapping
{
    private array $normalizedColumnNameMapping;

    public function __construct(array $normalizedToOriginalColumnMapping)
    {
        foreach ($normalizedToOriginalColumnMapping as $normalizedColumnName => $originalColumnNames) {
            foreach ($originalColumnNames as $originalColumnName) {
                $this->normalizedColumnNameMapping[mb_strtolower(trim($originalColumnName))] = $normalizedColumnName;
            }
        }
    }

    public function getNormalizedColumnName(string $originalColumnName): ?string
    {
        $columnName = mb_strtolower(trim($originalColumnName));

        return $this->normalizedColumnNameMapping[$columnName] ?? null;
    }
}
