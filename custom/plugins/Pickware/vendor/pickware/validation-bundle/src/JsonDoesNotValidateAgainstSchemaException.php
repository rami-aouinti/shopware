<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ValidationBundle;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;

class JsonDoesNotValidateAgainstSchemaException extends JsonValidatorException implements JsonApiErrorsSerializable
{
    /** @var SchemaError[]  */
    private array $schemaErrors;

    private JsonApiErrors $jsonApiErrors;

    public function __construct(array $errors, array $data)
    {
        $this->schemaErrors = array_map(
            fn($error) => SchemaError::fromValidationError($error),
            $errors,
        );

        // Create a base error message without including schema error details
        $mainErrorTitle = [
            'en' => 'JSON validation failed',
            'de' => 'JSON-Validierung fehlgeschlagen',
        ];
        $mainError = new LocalizableJsonApiError([
            'title' => $mainErrorTitle,
            'detail' => [
                'en' => 'The provided JSON does not validate against the schema.',
                'de' => 'Das bereitgestellte JSON entspricht nicht dem Schema.',
            ],
            'meta' => [
                'data' => $data,
                'schemaErrorsCount' => count($this->schemaErrors),
            ],
        ]);

        // Create a JsonApiErrors object with the main error and all schema errors
        $this->jsonApiErrors = new JsonApiErrors([$mainError]);

        // Add each schema error and its sub-errors as separate errors
        foreach ($this->schemaErrors as $schemaError) {
            $this->addSchemaErrorAndSubErrorsToJsonApiErrors($schemaError, $this->jsonApiErrors);
        }

        parent::__construct(new LocalizableJsonApiError([
            'title' => $mainErrorTitle,
            'detail' => $this->jsonApiErrors->getThrowableMessage(),
        ]));
    }

    public function getSchemaErrors(): array
    {
        return $this->schemaErrors;
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    /**
     * Recursively adds a SchemaError and all its sub-errors to the JsonApiErrors object
     */
    private function addSchemaErrorAndSubErrorsToJsonApiErrors(SchemaError $schemaError, JsonApiErrors $jsonApiErrors): void
    {
        // Add the schema error itself
        $jsonApiErrors->addError($schemaError->serializeToJsonApiError());

        // Recursively add all sub-errors
        foreach ($schemaError->getSubErrors() as $subError) {
            $this->addSchemaErrorAndSubErrorsToJsonApiErrors($subError, $jsonApiErrors);
        }
    }
}
