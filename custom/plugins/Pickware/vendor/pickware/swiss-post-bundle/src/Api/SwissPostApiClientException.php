<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Api;

use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use JsonException;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Pickware\PhpStandardLibrary\Json\Json;
use Throwable;

class SwissPostApiClientException extends Exception implements JsonApiErrorsSerializable
{
    public function __construct(
        private readonly JsonApiErrors $jsonApiErrors,
        ?Throwable $previous = null,
    ) {
        $message = implode(' ', array_map(fn(JsonApiError $error) => $error->getDetail(), $jsonApiErrors->getErrors()));

        parent::__construct($message, 0, $previous);
    }

    public static function fromClientException(ClientException $e): self
    {
        $errors = self::createJsonApiErrorsFromBadResponseException($e);
        array_unshift($errors, new LocalizableJsonApiError([
            'detail' => [
                'en' => 'The SwissPost API could not process the request sent by us.',
                'de' => 'Die SwissPost API konnte die von uns gesendete Anfrage nicht verarbeiten.',
            ],
        ]));

        return new self(new JsonApiErrors($errors), $e);
    }

    public static function fromServerException(ServerException $e): self
    {
        if ($e->getResponse()->getHeader('content-type') === ['application/json;charset=UTF-8']) {
            $errors = self::createJsonApiErrorsFromBadResponseException($e);
        } else {
            $errors = [
                new JsonApiError([
                    'detail' => (string) $e->getResponse()->getBody(),
                ]),
            ];
        }

        array_unshift($errors, new LocalizableJsonApiError([
            'detail' => [
                'en' => 'The SwissPost API request failed due to an unexpected SwissPost server error.',
                'de' => 'Die SwissPost API-Anfrage ist aufgrund eines unerwarteten SwissPost-Serverfehlers fehlgeschlagen.',
            ],
        ]));

        return new self(new JsonApiErrors($errors), $e);
    }

    private static function createJsonApiErrorsFromBadResponseException(BadResponseException $e): array
    {
        $responseBody = $e->getResponse()->getBody();

        try {
            $json = Json::decodeToArray((string) $responseBody);
        } catch (JsonException $e) {
            return [
                new JsonApiError([
                    'code' => 'Unknown Error',
                    'detail' => (string) $responseBody,
                    'meta' => [
                        'decodingError' => $e->getMessage(),
                    ],
                ]),
            ];
        }

        if (!array_key_exists('error', $json)) {
            return array_map(function(array $error) {
                if ($error['error'] === 'Pattern') {
                    $detail = [
                        'de' => sprintf(
                            'Das Feld %s enthält den unzulässigen Wert "%s".',
                            $error['field'],
                            $error['rejectedValue'],
                        ),
                        'en' => sprintf(
                            'The field %s contains the rejected value "%s".',
                            $error['field'],
                            $error['rejectedValue'],
                        ),
                    ];
                } else {
                    $detail = [
                        'de' => sprintf(
                            'Das Feld %s verursacht einen unbekannten Fehler',
                            $error['field'],
                        ),
                        'en' => sprintf(
                            'The field %s created an unknown error.',
                            $error['field'],
                        ),
                    ];
                }

                return new LocalizableJsonApiError([
                    'code' => $error['error'],
                    'detail' => $detail,
                    'meta' => [
                        'rejectedValue' => $error['rejectedValue'],
                        'field' => $error['field'],
                    ],
                ]);
            }, $json);
        }

        return [
            new JsonApiError([
                'code' => $json['error'],
                'detail' => $json['error_description'],
            ]),
        ];
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }
}
