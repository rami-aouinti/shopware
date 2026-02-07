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

use Exception;
use JsonException;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Throwable;

class JsonValidatorException extends Exception implements JsonApiErrorSerializable
{
    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError, ?Throwable $previous = null)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail() ?? 'JSON validation failed', 0, $previous);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function invalidJson(JsonException $previous): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Invalid JSON',
                    'de' => 'Ungültiges JSON',
                ],
                'detail' => [
                    'en' => 'JSON could not be parsed as the syntax is invalid.',
                    'de' => 'Das JSON konnte nicht geparst werden, da die Syntax ungültig ist.',
                ],
            ]),
            $previous,
        );
    }
}
