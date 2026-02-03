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

use Opis\JsonSchema\ValidationError;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\PhpStandardLibrary\Json\Json;

class SchemaError
{
    /**
     * @param string $keyword The validation keyword that failed (e.g., 'type', 'required', 'minimum')
     * @param string $path The JSON path where the validation error occurred
     * @param mixed $data The actual data value that caused the validation error
     * @param array<mixed> $keywordArgs Additional arguments specific to the validation keyword
     * @param array<SchemaError> $subErrors Array of nested SchemaError objects for complex validation failures
     */
    public function __construct(
        private readonly string $keyword,
        private readonly string $path,
        private readonly mixed $data,
        private readonly array $keywordArgs = [],
        private readonly array $subErrors = [],
    ) {}

    public static function fromValidationError(ValidationError $error): self
    {
        $data = $error->data();
        $path = implode('.', $error->dataPointer());
        $keyword = $error->keyword();
        $keywordArgs = $error->keywordArgs();

        // Process sub-errors recursively
        $subErrors = [];
        if ($error->subErrorsCount() > 0) {
            foreach ($error->subErrors() as $subError) {
                $subErrors[] = self::fromValidationError($subError);
            }
        }

        return new self($keyword, $path, $data, $keywordArgs, $subErrors);
    }

    public function serializeToJsonApiError(): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'detail' => $this->getDetailedErrorMessage(),
            'meta' => [
                'keyword' => $this->keyword,
                'path' => $this->path,
                'data' => $this->data,
                'keywordArgs' => $this->keywordArgs,
                'subErrors' => array_map(fn(SchemaError $error) => $error->serializeToJsonApiError(), $this->subErrors),
            ],
        ]);
    }

    /**
     * @return array{de: string, en: string}
     */
    public function getDetailedErrorMessage(): array
    {
        $messageEn = sprintf("Validation error at path '%s'", $this->path);
        $messageDe = sprintf("Validierungsfehler bei Pfad '%s'", $this->path);

        switch ($this->keyword) {
            case 'type':
                $expectedType = is_string($this->keywordArgs['expected'] ?? null) ? $this->keywordArgs['expected'] : 'unknown';
                $actualType = is_string($this->keywordArgs['used'] ?? null) ? $this->keywordArgs['used'] : 'unknown';
                $messageEn .= sprintf("\nExpected type: %s, Actual type: %s", $expectedType, $actualType);
                $messageDe .= sprintf("\nErwarteter Typ: %s, Tatsächlicher Typ: %s", $expectedType, $actualType);
                break;
            case 'minimum':
            case 'maximum':
                $dataStr = is_scalar($this->data) ? (string) $this->data : Json::stringify($this->data);
                $messageEn .= sprintf("\nValue: %s", $dataStr);
                $messageDe .= sprintf("\nWert: %s", $dataStr);
                break;
            case 'minLength':
            case 'maxLength':
                $lengthValue = $this->keywordArgs['length'] ?? 0;
                $actualLength = is_int($lengthValue) ? $lengthValue : (is_numeric($lengthValue) ? (int) $lengthValue : 0);
                $dataStr = is_scalar($this->data) ? (string) $this->data : Json::stringify($this->data);
                $messageEn .= sprintf("\nValue: '%s', Length: %d", $dataStr, $actualLength);
                $messageDe .= sprintf("\nWert: '%s', Länge: %d", $dataStr, $actualLength);
                break;
            case 'required':
                $missingValue = $this->keywordArgs['missing'] ?? '';
                $missingProperty = is_string($missingValue) ? $missingValue : Json::stringify($missingValue);
                $messageEn .= sprintf("\nMissing required property: %s", $missingProperty);
                $messageDe .= sprintf("\nFehlende erforderliche Eigenschaft: %s", $missingProperty);
                break;
            case 'enum':
                $allowedValues = $this->keywordArgs['choices'] ?? [];
                $dataStr = is_scalar($this->data) ? (string) $this->data : Json::stringify($this->data);
                $allowedValuesStr = Json::stringify($allowedValues);
                $messageEn .= sprintf("\nValue: %s, Allowed values: %s", $dataStr, $allowedValuesStr);
                $messageDe .= sprintf("\nWert: %s, Erlaubte Werte: %s", $dataStr, $allowedValuesStr);
                break;
            case 'pattern':
                $patternValue = $this->keywordArgs['pattern'] ?? '';
                $pattern = is_string($patternValue) ? $patternValue : Json::stringify($patternValue);
                $dataStr = is_scalar($this->data) ? (string) $this->data : Json::stringify($this->data);
                $messageEn .= sprintf("\nValue: '%s', Pattern: %s", $dataStr, $pattern);
                $messageDe .= sprintf("\nWert: '%s', Muster: %s", $dataStr, $pattern);
                break;
            default:
                // For other keywords, just include the data
                if (!is_array($this->data) && !is_object($this->data)) {
                    $dataStr = is_scalar($this->data) ? (string) $this->data : Json::stringify($this->data);
                    $messageEn .= sprintf("\nValue: %s", $dataStr);
                    $messageDe .= sprintf("\nWert: %s", $dataStr);
                }
                break;
        }

        if ($this->getSubErrorsCount() > 0) {
            $messageEn .= sprintf("\nSub-errors (%d):", $this->getSubErrorsCount());
            $messageDe .= sprintf("\nUnterfehler (%d):", $this->getSubErrorsCount());
            foreach ($this->subErrors as $index => $subError) {
                $subMessage = $subError->getDetailedErrorMessage();
                // Extract the first line (with the path) for both languages
                $linesEn = explode("\n", $subMessage['en']);
                $linesDe = explode("\n", $subMessage['de']);
                $firstLineEn = array_shift($linesEn);
                $firstLineDe = array_shift($linesDe);

                // Add the first line with numbering
                $messageEn .= sprintf("\n%d. %s", $index + 1, $firstLineEn);
                $messageDe .= sprintf("\n%d. %s", $index + 1, $firstLineDe);

                // Indent the rest of the lines
                if (!empty($linesEn)) {
                    $indentedLinesEn = array_map(fn($line) => '    ' . $line, $linesEn);
                    $messageEn .= "\n" . implode("\n", $indentedLinesEn);
                }
                if (!empty($linesDe)) {
                    $indentedLinesDe = array_map(fn($line) => '    ' . $line, $linesDe);
                    $messageDe .= "\n" . implode("\n", $indentedLinesDe);
                }
            }
        }

        return [
            'en' => $messageEn,
            'de' => $messageDe,
        ];
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return array<mixed>
     */
    public function getKeywordArgs(): array
    {
        return $this->keywordArgs;
    }

    /**
     * @return array<SchemaError>
     */
    public function getSubErrors(): array
    {
        return $this->subErrors;
    }

    public function getSubErrorsCount(): int
    {
        return count($this->subErrors);
    }
}
