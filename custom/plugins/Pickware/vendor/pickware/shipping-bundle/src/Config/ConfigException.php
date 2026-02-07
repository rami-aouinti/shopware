<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class ConfigException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_SHIPPING__CONFIG__';
    public const ERROR_CODE_FIELD_MISSING = self::ERROR_CODE_NAMESPACE . 'FIELD_MISSING';
    public const ERROR_CODE_FIELD_INVALID_FORMATTED = self::ERROR_CODE_NAMESPACE . 'FIELD_INVALID_FORMATTED';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function invalidFormattedField(string $configDomain, string $fieldName): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_FIELD_INVALID_FORMATTED,
            'title' => 'Configuration field has invalid format',
            'detail' => sprintf(
                'The value of field "%s" in config domain "%s" has an invalid format.',
                $fieldName,
                $configDomain,
            ),
            'meta' => [
                'configDomain' => $configDomain,
                'field' => $fieldName,
            ],
        ]));
    }

    public static function missingConfigurationField(string $configDomain, string $fieldName): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_FIELD_MISSING,
            'title' => 'Configuration field missing',
            'detail' => sprintf(
                'The configuration for domain "%s" is missing following field: "%s".',
                $configDomain,
                $fieldName,
            ),
            'meta' => [
                'configDomain' => $configDomain,
                'field' => $fieldName,
            ],
        ]));
    }
}
