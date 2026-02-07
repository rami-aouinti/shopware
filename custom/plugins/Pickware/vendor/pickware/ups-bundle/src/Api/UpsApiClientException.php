<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api;

use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Pickware\PhpStandardLibrary\Json\Json;
use Throwable;

class UpsApiClientException extends Exception implements JsonApiErrorsSerializable
{
    private JsonApiErrors $jsonApiErrors;

    public function __construct(JsonApiErrors $errors, ?Throwable $previous = null)
    {
        $this->jsonApiErrors = $errors;
        $message = implode(' ', array_map(fn(JsonApiError $error) => $error->getDetail(), $errors->getErrors()));

        parent::__construct($message, 0, $previous);
    }

    public static function fromClientException(ClientException $e): self
    {
        $errors = self::createJsonApiErrorsFromBadResponseException($e);
        array_unshift($errors, new JsonApiError([
            'detail' => 'The UPS API could not process the request sent by us.',
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

        array_unshift($errors, new JsonApiError([
            'detail' => 'The UPS API request failed due to an unexpected UPS server error.',
        ]));

        return new self(new JsonApiErrors($errors), $e);
    }

    private static function createJsonApiErrorsFromBadResponseException(BadResponseException $e): array
    {
        $responseBody = $e->getResponse()->getBody();
        $json = Json::decodeToArray((string) $responseBody);

        return array_map(fn(array $error) => new JsonApiError([
            'code' => $error['code'],
            'detail' => $error['message'],
        ]), $json['response']['errors'] ?? []);
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }
}
