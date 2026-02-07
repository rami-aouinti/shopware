<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class UsageReportApiClientException extends Exception implements JsonApiErrorSerializable
{
    protected function __construct(
        private readonly LocalizableJsonApiError $jsonApiError,
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->jsonApiError->getDetail(), 0, $previous);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function createInvalidRequestError(array $usageReportJsonApiError): self
    {
        $usageReportApiErrorTranslations = $usageReportJsonApiError['meta']['translations'] ?? [];

        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => $usageReportApiErrorTranslations['de']['title'] ?? $usageReportJsonApiError['title'],
                'en' => $usageReportApiErrorTranslations['en']['title'] ?? $usageReportJsonApiError['title'],
            ],
            'detail' => [
                'de' => $usageReportApiErrorTranslations['de']['detail'] ?? $usageReportJsonApiError['detail'],
                'en' => $usageReportApiErrorTranslations['en']['detail'] ?? $usageReportJsonApiError['detail'],
            ],
            'meta' => [
                'usageReportJsonApiError' => $usageReportJsonApiError,
            ],
        ]));
    }

    public static function createInvalidResponseCodeError(ResponseInterface $response): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Unbekannter Fehler',
                'en' => 'Unknown error',
            ],
            'detail' => [
                'de' => 'Ein unbekannter Fehler ist aufgetreten. Bitte kontaktiere den Pickware Support.',
                'en' => 'An unknown error occurred. Please contact Pickware support.',
            ],
            'meta' => [
                'response' => [
                    'statusCode' => $response->getStatusCode(),
                    'reasonPhrase' => $response->getReasonPhrase(),
                    'body' => (string) $response->getBody(),
                ],
            ],
        ]));
    }

    public static function unknownError(Throwable $throwable): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Unbekannter Fehler',
                    'en' => 'Unknown error',
                ],
                'detail' => [
                    'de' => 'Ein unbekannter Fehler ist aufgetreten. Bitte kontaktiere den Pickware Support.',
                    'en' => 'An unknown error occurred. Please contact Pickware support.',
                ],
            ]),
            previous: $throwable,
        );
    }
}
