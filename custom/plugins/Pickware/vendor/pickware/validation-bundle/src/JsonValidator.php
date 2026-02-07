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

use JsonException;
use Opis\JsonSchema\Validator;
use Pickware\PhpStandardLibrary\Json\Json;

class JsonValidator
{
    /**
     * @deprecated Will be removed in 4.0.0. Use {@link self::validateJsonStringAgainstJsonSchema()} instead.
     */
    public function validateJsonAgainstSchema(string $json, string $jsonSchemaFilePath): void
    {
        $this->validateJsonStringAgainstJsonSchema($json, JsonSchema::createFromFile($jsonSchemaFilePath));
    }

    public function validateJsonStringAgainstJsonSchema(string $json, JsonSchema $jsonSchema): void
    {
        try {
            $object = Json::decodeToObject($json);
        } catch (JsonException $exception) {
            throw JsonValidatorException::invalidJson($exception);
        }

        $validator = new Validator();
        $result = $validator->schemaValidation(
            data: $object,
            schema: $jsonSchema->getOpisSchema(),
        );

        if ($result->hasErrors()) {
            throw new JsonDoesNotValidateAgainstSchemaException(
                $result->getErrors(),
                Json::decodeToArray($json),
            );
        }
    }
}
