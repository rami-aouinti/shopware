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
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PhpStandardLibrary\Json\Json;

class ImportExportException extends Exception
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__IMPORT_EXPORT__';
    private const ERROR_ERRORS_COULD_NOT_BE_ENCODED = self::ERROR_CODE_NAMESPACE . 'ERRORS_COULD_NOT_BE_ENCODED';
    private const ERROR_CODE_CONFIG_PARAMETER_NOT_SET = self::ERROR_CODE_NAMESPACE . 'CONFIG_PARAMETER_NOT_SET';
    private const ERROR_CODE_READER_TECHNICAL_NAME_MISSING = self::ERROR_CODE_NAMESPACE . 'READER_TECHNICAL_NAME_MISSING';
    private const ERROR_CODE_FILE_READER_WITHOUT_DOCUMENT = self::ERROR_CODE_NAMESPACE . 'FILE_READER_WITHOUT_DOCUMENT';
    private const ERROR_CODE_MIMETYPE_MISMATCH = self::ERROR_CODE_NAMESPACE . 'MIMETYPE_MISMATCH';
    private const ERROR_CODE_DOCUMENT_WITHOUT_FILE_READER = self::ERROR_CODE_NAMESPACE . 'DOCUMENT_WITHOUT_FILE_READER';
    private const ERROR_CODE_WRITER_TECHNICAL_NAME_MISSING = self::ERROR_CODE_NAMESPACE . 'WRITER_TECHNICAL_NAME_MISSING';
    private const ERROR_CODE_FILE_WRITER_WITHOUT_FILE_EXPORTER = self::ERROR_CODE_NAMESPACE . 'FILE_WRITER_WITHOUT_FILE_EXPORTER';
    private const ERROR_CODE_HEADER_WRITER_WITHOUT_HEADER_EXPORTER = self::ERROR_CODE_NAMESPACE . 'HEADER_WRITER_WITHOUT_HEADER_EXPORTER';

    public static function createErrorsCouldNotBeEncodedError(JsonApiErrors $errors): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_ERRORS_COULD_NOT_BE_ENCODED,
            'title' => 'Error could not be encoded',
            'detail' => 'A previous error could not be encoded.',
            'meta' => [
                'parsedErrors' => Json::stringify($errors, \JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            ],
        ]);
    }

    public static function createConfigParameterNotSetError(string $parameterKey): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_CONFIG_PARAMETER_NOT_SET,
            'title' => 'Config parameter not set',
            'detail' => sprintf('The config parameter "%s" is not set but required.', $parameterKey),
            'meta' => ['parameterKey' => $parameterKey],
        ]);
    }

    public static function createReaderTechnicalNameNotSetError(): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_READER_TECHNICAL_NAME_MISSING,
            'title' => 'Reader technical name not set in config',
            'detail' => 'The import must specify the reader technical name in its config',
        ]);
    }

    public static function createFileReaderWithoutDocumentError(string $readerTechnicalName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_FILE_READER_WITHOUT_DOCUMENT,
            'title' => 'File reader specified without attached document',
            'detail' => sprintf(
                'The specified reader with technical name "%s" supports reading files, yet it has no document attached',
                $readerTechnicalName,
            ),
            'meta' => ['readerTechnicalName' => $readerTechnicalName],
        ]);
    }

    public static function createMimetypeMismatchError(string $fileReaderMimetype, string $documentMimetype): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_MIMETYPE_MISMATCH,
            'title' => 'File reader does not support document mimetype',
            'detail' => sprintf(
                'The specified reader supports only the mimetype "%s", but the document has the mimetype "%s".',
                $fileReaderMimetype,
                $documentMimetype,
            ),
            'meta' => [
                'fileReaderMimetype' => $fileReaderMimetype,
                'documentMimetype' => $documentMimetype,
            ],
        ]);
    }

    public static function createDocumentWithoutFileReaderError(string $readerTechnicalName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_DOCUMENT_WITHOUT_FILE_READER,
            'title' => 'Document attached without specified file reader',
            'detail' => sprintf(
                'The import has a document attached, but the reader with technical name "%s" does not support file reading',
                $readerTechnicalName,
            ),
            'meta' => ['readerTechnicalName' => $readerTechnicalName],
        ]);
    }

    public static function createWriterTechnicalNameNotSetError(): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_WRITER_TECHNICAL_NAME_MISSING,
            'title' => 'Writer technical name not set in config',
            'detail' => 'The import must specify the writer technical name in its config',
        ]);
    }

    public static function createFileWriterWithoutFileExporterError(string $writerTechnicalName, string $exporterTechnicalName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_FILE_WRITER_WITHOUT_FILE_EXPORTER,
            'title' => 'Specified file writer without chosen file exporter',
            'detail' => sprintf(
                'The writer with technical name %s writes to a file, but the exporter with technical name "%s" '
                . 'does not support exporting to a file',
                $writerTechnicalName,
                $exporterTechnicalName,
            ),
            'meta' => [
                'writerTechnicalName' => $writerTechnicalName,
                'exporterTechnicalName' => $exporterTechnicalName,
            ],
        ]);
    }

    public static function createHeaderWriterWithoutHeaderExporterError(string $writerTechnicalName, string $exporterTechnicalName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_HEADER_WRITER_WITHOUT_HEADER_EXPORTER,
            'title' => 'Specified header writer without chosen header exporter',
            'detail' => sprintf(
                'The writer with technical name %s writes a header, but the exporter with technical name "%s" '
                . 'does not support providing a header',
                $writerTechnicalName,
                $exporterTechnicalName,
            ),
            'meta' => [
                'writerTechnicalName' => $writerTechnicalName,
                'exporterTechnicalName' => $exporterTechnicalName,
            ],
        ]);
    }
}
