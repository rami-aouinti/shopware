<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Throwable;

class CashRegisterException extends Exception implements JsonApiErrorsSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_POS__CASH_REGISTER__';
    public const ERROR_CODE_MAXIMUM_CASH_REGISTER_PREFIX_EXCEEDED = self::ERROR_CODE_NAMESPACE . 'MAXIMUM_CASH_REGISTER_PREFIX_EXCEEDED';
    public const ERROR_CODE_CASH_REGISTER_PREFIX_PASSED = self::ERROR_CODE_NAMESPACE . 'CASH_REGISTER_PREFIX_PASSED';

    private JsonApiErrors $jsonApiErrors;

    public function __construct(JsonApiErrors $jsonApiErrors, ?Throwable $previous = null)
    {
        $this->jsonApiErrors = $jsonApiErrors;
        parent::__construct($jsonApiErrors->getThrowableMessage(), 0, $previous);
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public static function maximumCashRegisterPrefixExceeded(string $cashRegisterName): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::ERROR_CODE_MAXIMUM_CASH_REGISTER_PREFIX_EXCEEDED,
                    'title' => [
                        'en' => 'Maximum cash register prefix exceeded',
                        'de' => 'Maximaler Kassenpräfix erreicht',
                    ],
                    'detail' => [
                        'en' => sprintf(
                            'The cash register "%s" could not be created, because it would exceed the maximum cash register prefix of 999.',
                            $cashRegisterName,
                        ),
                        'de' => sprintf(
                            'Die Kasse "%s" konnte nicht erstellt werden, da sie den maximalen Kassenpräfix von 999 überschreiten würde.',
                            $cashRegisterName,
                        ),
                    ],
                    'meta' => ['name' => $cashRegisterName],
                ]),
            ]),
        );
    }

    public static function cashRegisterPrefixPassed(): self
    {
        return new self(
            new JsonApiErrors([
                new LocalizableJsonApiError([
                    'code' => self::ERROR_CODE_CASH_REGISTER_PREFIX_PASSED,
                    'title' => [
                        'en' => 'Cash register prefix passed',
                        'de' => 'Kassenpräfix übergeben',
                    ],
                    'detail' => [
                        'en' => 'The cash register prefix is generated automatically and must not be passed.',
                        'de' => 'Der Kassenpräfix wird automatisch generiert und darf nicht übergeben werden.',
                    ],
                ]),
            ]),
        );
    }
}
