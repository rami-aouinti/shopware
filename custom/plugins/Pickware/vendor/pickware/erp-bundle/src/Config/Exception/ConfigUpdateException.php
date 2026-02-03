<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Config\Exception;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Symfony\Component\HttpFoundation\Response;

class ConfigUpdateException extends Exception implements JsonApiErrorSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__CONFIG_UPDATE__';
    private const ERROR_CODE_UPDATE_NOT_ALLOWED = self::ERROR_CODE_NAMESPACE . 'UPDATE_NOT_ALLOWED';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $apiError)
    {
        parent::__construct($apiError->getTitle());

        $this->jsonApiError = $apiError;
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function updateConfigNotAllowed(string $fieldName, string $value, JsonApiErrors $jsonApiErrors): self
    {
        return new self(
            new JsonApiError([
                'status' => Response::HTTP_BAD_REQUEST,
                'code' => self::ERROR_CODE_UPDATE_NOT_ALLOWED,
                'title' => 'Config update not allowed',
                'detail' => sprintf(
                    'The pre validation for updating the field "%s" with value "%s" failed.',
                    $fieldName,
                    $value,
                ),
                'meta' => [
                    'errors' => $jsonApiErrors->getErrors(),
                    'key' => $fieldName,
                    'value' => $value,
                ],
            ]),
        );
    }
}
