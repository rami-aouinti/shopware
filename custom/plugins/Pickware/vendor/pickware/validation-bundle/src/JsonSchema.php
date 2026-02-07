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

use InvalidArgumentException;
use JsonException;
use Opis\JsonSchema\Schema;
use Pickware\PhpStandardLibrary\Json\Json;
use RuntimeException;
use stdClass;

/**
 * @phpstan-type ResolvedSchema object{properties?: array<string, mixed>, required?: string[], additionalProperties?: bool}
 */
class JsonSchema
{
    public function __construct(private readonly Schema $schema) {}

    /**
     * @param array<string, mixed> $schemaArray
     */
    public static function createFromSchemaArray(array $schemaArray): self
    {
        $jsonSchema = Json::stringify($schemaArray);
        $schema = Schema::fromJsonString($jsonSchema);

        return new self($schema);
    }

    public static function createFromJsonString(string $jsonString): self
    {
        $schema = Schema::fromJsonString($jsonString);

        return new self($schema);
    }

    public static function createFromFile(string $jsonSchemaFilePath): self
    {
        if (!file_exists($jsonSchemaFilePath)) {
            throw new RuntimeException(sprintf('Could not find the requested JSON schema in \'%s\'', $jsonSchemaFilePath));
        }
        $fileContents = file_get_contents($jsonSchemaFilePath);
        if ($fileContents === false) {
            throw new RuntimeException(sprintf('Could not read the JSON schema file at \'%s\'', $jsonSchemaFilePath));
        }

        try {
            $schema = Json::decodeToObject($fileContents);
            if (!($schema instanceof stdClass)) {
                throw new InvalidArgumentException(
                    sprintf('JSON schema does not contain a valid JSON object. File path: %s', $jsonSchemaFilePath),
                );
            }
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf('JSON schema does not contain valid json. File path: %s', $jsonSchemaFilePath),
                0,
                $exception,
            );
        }

        return new self(new Schema($schema));
    }

    /**
     * @return string[]
     */
    public function getRequiredProperties(): array
    {
        /** @var ResolvedSchema $schema */
        $schema = $this->schema->resolve();

        return $schema->required ?? [];
    }

    /**
     * @return array<string>
     */
    public function getProperties(): array
    {
        /** @var ResolvedSchema $schema */
        $schema = $this->schema->resolve();

        return array_keys((array) ($schema->properties ?? []));
    }

    public function allowsAdditionalProperties(): bool
    {
        /** @var ResolvedSchema $schema */
        $schema = $this->schema->resolve();

        return $schema->additionalProperties ?? true;
    }

    public function getOpisSchema(): Schema
    {
        return $this->schema;
    }
}
