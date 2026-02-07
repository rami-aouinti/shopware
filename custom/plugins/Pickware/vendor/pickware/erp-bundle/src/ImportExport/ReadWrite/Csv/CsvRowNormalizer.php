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
class CsvRowNormalizer
{
    private CsvRowNormalizedColumnMapping $csvRowNormalizedColumnMapping;

    public function __construct($csvRowNormalizedColumnMapping)
    {
        $this->csvRowNormalizedColumnMapping = $csvRowNormalizedColumnMapping;
    }

    public function normalizeRow(array $row): array
    {
        $row = array_combine(
            array_map('trim', array_keys($row)),
            array_map('trim', array_values($row)),
        );

        $row = $this->mapTranslations($row);

        return $row;
    }

    public function normalizeColumnNames(array $columnNames): array
    {
        return array_values(array_filter(array_map(fn(string $columnName) => $this->csvRowNormalizedColumnMapping->getNormalizedColumnName($columnName), $columnNames)));
    }

    /**
     * @param string[] List of the original column names
     * @return string[][] Mapping [normalized column name] => [original column names[]]
     */
    public function mapNormalizedToOriginalColumnNames(array $originalColumnNames): array
    {
        $mapping = [];
        foreach ($originalColumnNames as $originalColumnName) {
            $normalizedColumnName = $this->csvRowNormalizedColumnMapping->getNormalizedColumnName(
                // If the user, for whatever reason, adds a column title with a number as first character, we format it
                (string) $originalColumnName,
            );
            if ($normalizedColumnName === null) {
                continue;
            }
            if (!isset($mapping[$normalizedColumnName])) {
                $mapping[$normalizedColumnName] = [];
            }
            if (!in_array($originalColumnName, $mapping[$normalizedColumnName], true)) {
                $mapping[$normalizedColumnName][] = $originalColumnName;
            }
        }

        return $mapping;
    }

    public static function isIntegerString(string $string): bool
    {
        return preg_match('/^[+-]?\\d+$/', $string) === 1;
    }

    public static function isFloatString(string $string): bool
    {
        return preg_match('/^[+-]?\\d+(\\.\\d+)?$/', $string) === 1;
    }

    public static function isBooleanString(string $string): bool
    {
        return preg_match('/true|false/', $string) === 1;
    }

    private function mapTranslations(array $row): array
    {
        $row = array_combine(
            array_map(fn(string $columnName) => $this->csvRowNormalizedColumnMapping->getNormalizedColumnName($columnName), array_keys($row)),
            array_values($row),
        );
        unset($row['']);

        return $row;
    }
}
