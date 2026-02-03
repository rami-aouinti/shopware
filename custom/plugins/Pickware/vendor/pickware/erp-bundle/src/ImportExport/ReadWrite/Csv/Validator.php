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

use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\ValidationBundle\JsonDoesNotValidateAgainstSchemaException;
use Pickware\ValidationBundle\JsonSchema;
use Pickware\ValidationBundle\JsonValidator;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class Validator
{
    private JsonSchema $validationSchema;

    /**
     * @param JsonValidator $validator Argument will be non-optional in v2.0.0.
     * @param array<string, mixed> $validationSchemaArray
     */
    public function __construct(
        private readonly CsvRowNormalizer $normalizer,
        array $validationSchemaArray,
        private readonly JsonValidator $validator = new JsonValidator(),
    ) {
        $this->validationSchema = JsonSchema::createFromSchemaArray($validationSchemaArray);
    }

    /**
     * @deprecated tag:next-major parameter $context can be removed as it is unused
     * @param list<string> $headerRow
     */
    public function validateHeaderRow(array $headerRow, ?Context $context = null): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        // Check for missing header
        $actualColumns = $this->normalizer->normalizeColumnNames($headerRow);
        if (count($actualColumns) === 0) {
            $errors->addError(CsvErrorFactory::missingHeaderRow());

            return $errors;
        }

        // Check for required columns
        $missingColumns = array_values(array_diff($this->validationSchema->getRequiredProperties(), $actualColumns));
        foreach ($missingColumns as $missingColumn) {
            $errors->addError(CsvErrorFactory::missingColumn($missingColumn));
        }

        $additionalProperties = $this->validationSchema->allowsAdditionalProperties();

        if (!$additionalProperties) {
            $invalidColumns = array_values(array_diff($actualColumns, $this->validationSchema->getProperties()));
            foreach ($invalidColumns as $invalidColumn) {
                $errors->addError(CsvErrorFactory::invalidColumn($invalidColumn));
            }
        }

        // Check for duplicated columns
        $columnCounts = array_count_values($actualColumns);
        $normalizedToOriginalColumnNameMapping = $this->normalizer->mapNormalizedToOriginalColumnNames($headerRow);
        foreach ($columnCounts as $normalizedColumnName => $columnCount) {
            if ($columnCount === 1) {
                continue;
            }
            $errors->addError(CsvErrorFactory::duplicatedColumns(
                $normalizedColumnName,
                $normalizedToOriginalColumnNameMapping[$normalizedColumnName],
            ));
        }

        return $errors;
    }

    /**
     * @deprecated tag:next-major parameter $normalizedToOriginalColumnNameMapping can be removed as it is computed internally
     * @param array<string, mixed> $normalizedRow
     * @param array<array<string>> $normalizedToOriginalColumnNameMapping
     */
    public function validateRow(array $normalizedRow, ?array $normalizedToOriginalColumnNameMapping = null): JsonApiErrors
    {
        $normalizedToOriginalColumnNameMapping ??= $this->normalizer->mapNormalizedToOriginalColumnNames($normalizedRow);
        $errors = new JsonApiErrors();
        // Check for required cell values
        foreach ($this->validationSchema->getRequiredProperties() as $mandatoryColumn) {
            if ($normalizedRow[$mandatoryColumn] === '') {
                $errors->addError(CsvErrorFactory::missingCellValue(
                    $mandatoryColumn,
                    $normalizedToOriginalColumnNameMapping[$mandatoryColumn][0],
                ));
            }
        }

        if (count($errors)) {
            return $errors;
        }

        // Check the remaining constraints with validation
        try {
            $this->validator->validateJsonStringAgainstJsonSchema(
                json: Json::stringify($normalizedRow),
                jsonSchema: $this->validationSchema,
            );
        } catch (JsonDoesNotValidateAgainstSchemaException $e) {
            foreach ($e->getSchemaErrors() as $schemaError) {
                $errors->addError(CsvErrorFactory::invalidCellValue($schemaError));
            }
        }

        return $errors;
    }
}
