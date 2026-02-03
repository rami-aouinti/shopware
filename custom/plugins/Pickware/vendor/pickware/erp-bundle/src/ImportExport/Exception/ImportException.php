<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Exception;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Throwable;

class ImportException extends Exception
{
    private const ERROR_CODE_BATCH_IMPORT_FAILED = ImportExportException::ERROR_CODE_NAMESPACE . 'BATCH_IMPORT_FAILED';
    private const ERROR_CODE_ROW_IMPORT_FAILED = ImportExportException::ERROR_CODE_NAMESPACE . 'ROW_IMPORT_FAILED';
    private const ERROR_CODE_UNKNOWN_IMPORT_ERROR = ImportExportException::ERROR_CODE_NAMESPACE . 'UNKNOWN_IMPORT_ERROR';

    private JsonApiError $apiError;

    public function __construct(JsonApiError $apiError)
    {
        parent::__construct($apiError->getTitle());

        $this->apiError = $apiError;
    }

    public function getJsonApiError(): JsonApiError
    {
        return $this->apiError;
    }

    public static function batchImportError(Throwable $rootException, int $firstRowOfBatch, int $batchSize): self
    {
        return new self(
            new JsonApiError([
                'code' => self::ERROR_CODE_BATCH_IMPORT_FAILED,
                'title' => 'Error while batch processing an import chunk',
                'detail' => sprintf(
                    'The batch import failed. The error occurred in the rows between "%s" - "%s". ' .
                    'All previous batches were imported.',
                    $firstRowOfBatch,
                    $firstRowOfBatch + $batchSize,
                ),
                'meta' => [
                    'firstRowOfBatch' => $firstRowOfBatch,
                    'lastRowOfBatch' => $firstRowOfBatch + $batchSize,
                    'stackTrace' => $rootException->getTraceAsString() ?? '',
                    'message' => $rootException->getMessage() ?? '',
                ],
            ]),
        );
    }

    public static function rowImportError(Throwable $rootException, int $errorRow): self
    {
        return new self(
            new JsonApiError([
                'code' => self::ERROR_CODE_ROW_IMPORT_FAILED,
                'title' => 'Error while upsert an import row',
                'detail' => sprintf(
                    'The row import failed. The error occurred in row "%s". All previous rows were imported.',
                    $errorRow,
                ),
                'meta' => [
                    'errorRow' => $errorRow,
                    'stackTrace' => $rootException->getTraceAsString() ?? '',
                    'message' => $rootException->getMessage() ?? '',
                ],
            ]),
        );
    }

    public static function unknownError(Throwable $exception, int $errorRow): self
    {
        return new self(
            new JsonApiError([
                'code' => self::ERROR_CODE_UNKNOWN_IMPORT_ERROR,
                'title' => 'Unknown error during import',
                'detail' => sprintf(
                    'The error occurred in row "%s" or following. The previous rows have been processed.',
                    $errorRow,
                ),
                'meta' => [
                    'errorRow' => $errorRow,
                    'stackTrace' => $exception->getTraceAsString() ?? '',
                    'message' => $exception->getMessage() ?? '',
                ],
            ]),
        );
    }
}
