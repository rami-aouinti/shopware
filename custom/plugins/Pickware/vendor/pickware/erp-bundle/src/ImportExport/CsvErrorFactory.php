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

use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\ValidationBundle\SchemaError;
use Throwable;

class CsvErrorFactory
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__IMPORT_EXPORT__';
    private const ERROR_CODE_HEADER_ROW_MISSING = self::ERROR_CODE_NAMESPACE . 'HEADER_ROW_MISSING';
    private const ERROR_CODE_COLUMN_MISSING = self::ERROR_CODE_NAMESPACE . 'COLUMN_MISSING';
    private const ERROR_CODE_UNKNOWN_ERROR = self::ERROR_CODE_NAMESPACE . 'UNKNOWN_ERROR';
    private const ERROR_CODE_CELL_VALUE_MISSING = self::ERROR_CODE_NAMESPACE . 'CELL_VALUE_MISSING';
    private const ERROR_CODE_DUPLICATED_COLUMN = self::ERROR_CODE_NAMESPACE . 'DUPLICATED_COLUMN';
    private const ERROR_CODE_INVALID_COLUMN = self::ERROR_CODE_NAMESPACE . 'INVALID_COLUMN';
    private const ERROR_CODE_ROW_VALIDATION_FAILED_EXPECTED_ACTUAL = self::ERROR_CODE_NAMESPACE . 'ROW_VALIDATION_FAILED_EXPECTED_ACTUAL';
    private const ERROR_CODE_ROW_VALIDATION_FAILED_LIMIT_ACTUAL = self::ERROR_CODE_NAMESPACE . 'ROW_VALIDATION_FAILED_LIMIT_ACTUAL';
    private const ERROR_CODE_ROW_VALIDATION_FAILED_ONE_OF = self::ERROR_CODE_NAMESPACE . 'ROW_VALIDATION_FAILED_ONE_OF';

    public static function missingHeaderRow(): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_HEADER_ROW_MISSING,
            'title' => 'No CSV file header detected',
            'detail' => 'This CSV file seems to have no header row. Either the uploaded file is no valid CSV file, ' .
                'a wrong separator (e.g. , instead of ;) was used, the file has to many columns or the file ' .
                'immediately starts with a data row (headers are missing completely).',
        ]);
    }

    public static function missingColumn(string $normalizedColumnName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_COLUMN_MISSING,
            'title' => 'Missing column',
            'detail' => sprintf('This CSV file is missing the column "%s".', $normalizedColumnName),
            'meta' => [
                'normalizedColumnName' => $normalizedColumnName,
            ],
        ]);
    }

    public static function unknownError(Throwable $exception): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_UNKNOWN_ERROR,
            'title' => 'Unknown error during import/export',
            'detail' => $exception->getMessage(),
            'meta' => self::getExceptionMetaData($exception),
        ]);
    }

    private static function getExceptionMetaData(Throwable $e): array
    {
        $meta = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
        if ($e->getPrevious()) {
            $meta['previous'] = self::getExceptionMetaData($e->getPrevious());
        }

        return $meta;
    }

    public static function missingCellValue(string $normalizedColumnName, string $originalColumnName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_CELL_VALUE_MISSING,
            'title' => 'Missing cell value',
            'detail' => sprintf('This row is missing the value for the column "%s".', $originalColumnName),
            'meta' => [
                'normalizedColumnName' => $normalizedColumnName,
                'originalColumnName' => $originalColumnName,
            ],
        ]);
    }

    public static function invalidCellValue(SchemaError $error): JsonApiError
    {
        $jsonError = [
            'code' => self::ERROR_CODE_UNKNOWN_ERROR,
            'title' => 'Invalid row values',
            'detail' => 'This row contains invalid values.',
            'meta' => [
                'validationType' => $error->getKeyword(),
                'normalizedColumnName' => $error->getPath(),
            ],
        ];

        switch ($error->getKeyword()) {
            case 'type':
                $jsonError['code'] = self::ERROR_CODE_ROW_VALIDATION_FAILED_EXPECTED_ACTUAL;
                $jsonError['meta']['expected'] = $error->getKeywordArgs()['expected'];
                $jsonError['meta']['actual'] = $error->getKeywordArgs()['used'];
                break;
            case 'minimum':
                $jsonError['code'] = self::ERROR_CODE_ROW_VALIDATION_FAILED_LIMIT_ACTUAL;
                $jsonError['meta']['limit'] = $error->getKeywordArgs()['min'];
                $jsonError['meta']['actual'] = $error->getData();
                break;
            case 'minLength':
                $jsonError['code'] = self::ERROR_CODE_ROW_VALIDATION_FAILED_LIMIT_ACTUAL;
                $jsonError['meta']['limit'] = $error->getKeywordArgs()['min'];
                $jsonError['meta']['actual'] = $error->getKeywordArgs()['length'];
                break;
            case 'maxLength':
                $jsonError['code'] = self::ERROR_CODE_ROW_VALIDATION_FAILED_LIMIT_ACTUAL;
                $jsonError['meta']['limit'] = $error->getKeywordArgs()['max'];
                $jsonError['meta']['actual'] = $error->getKeywordArgs()['length'];
                break;
            case 'oneOf':
                $jsonError['code'] = self::ERROR_CODE_ROW_VALIDATION_FAILED_ONE_OF;
                $jsonError['meta']['actual'] = $error->getData();
                foreach ($error->getSubErrors() as $subError) {
                    $jsonError['meta']['subErrors'][] = self::invalidCellValue($subError)->jsonSerialize();
                }
                break;
        }

        return new JsonApiError($jsonError);
    }

    /**
     * @param string[] $originalColumnNames
     */
    public static function duplicatedColumns(string $normalizedColumnName, array $originalColumnNames): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_DUPLICATED_COLUMN,
            'title' => 'Duplicated column',
            'detail' => sprintf(
                'The following columns are duplicated or have the same meaning: %s',
                implode(', ', $originalColumnNames),
            ),
            'meta' => [
                'normalizedColumnName' => $normalizedColumnName,
                'originalColumnNames' => $originalColumnNames,
            ],
        ]);
    }

    public static function invalidColumn(string $invalidColumnName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_INVALID_COLUMN,
            'title' => 'Invalid column',
            'detail' => sprintf(
                'The column "%s" is not valid.',
                $invalidColumnName,
            ),
            'meta' => ['invalidColumnName' => $invalidColumnName],
        ]);
    }
}
