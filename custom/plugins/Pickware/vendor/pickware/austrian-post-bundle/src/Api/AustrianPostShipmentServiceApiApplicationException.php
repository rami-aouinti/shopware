<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;

class AustrianPostShipmentServiceApiApplicationException extends AustrianPostApiClientException
{
    public const ERROR_CODE_INVALID_CLIENT_ID = 'SN#10004';
    public const ERROR_CODE_INVALID_CUSTOMER_ID = 'SN#10005';

    public function __construct(private readonly string $errorCode, ?string $errorMessage)
    {
        parent::__construct(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Austrian Post API Fehler',
                    'en' => 'Austrian Post API error',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Die Anfrage an die Österreichische Post API war fehlerhaft. Fehler: %s',
                        $errorMessage ?? $errorCode,
                    ),
                    'en' => sprintf(
                        'The request to the Austrian Post API was erroneous. Error: %s',
                        $errorMessage ?? $errorCode,
                    ),
                ],
                'meta' => [
                    'errorCode' => $errorCode,
                    'errorMessage' => $errorMessage,
                ],
            ]),
        );
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function isAuthorizationError(): bool
    {
        return in_array($this->errorCode, [
            self::ERROR_CODE_INVALID_CLIENT_ID,
            self::ERROR_CODE_INVALID_CUSTOMER_ID,
        ], true);
    }
}
