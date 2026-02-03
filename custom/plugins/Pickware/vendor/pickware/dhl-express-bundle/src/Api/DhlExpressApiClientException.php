<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use JsonException;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\DhlExpressBundle\DhlExpressException;
use Pickware\PhpStandardLibrary\Json\Json;
use function Pickware\PhpStandardLibrary\Language\makeSentence;

class DhlExpressApiClientException extends DhlExpressException
{
    public static function fromClientException(ClientException $clientException): self
    {
        $message = self::getMessageFromBadResponseException($clientException);

        return new self(new LocalizableJsonApiError([
            'detail' => 'The DHL Express API could not process the request: ' . $message,
        ]), $clientException);
    }

    public static function fromServerException(ServerException $serverException): self
    {
        $message = self::getMessageFromBadResponseException($serverException);

        return new self(new LocalizableJsonApiError([
            'detail' => 'The DHL Express API is currently not available: ' . $message,
        ]), $serverException);
    }

    private static function getMessageFromBadResponseException(BadResponseException $e): string
    {
        try {
            $json = Json::decodeToArray((string)$e->getResponse()->getBody());
        } catch (JsonException) {
            return makeSentence((string)$e->getResponse()->getBody());
        }

        if (array_key_exists('reasons', $json)) {
            return implode(' ', array_map(fn(array $reason) => makeSentence($reason['msg']), $json['reasons']));
        }

        $message = makeSentence($json['detail']);
        if (array_key_exists('additionalDetails', $json)) {
            $message .= ' ' . implode(' ', array_map(makeSentence(...), $json['additionalDetails']));
        }

        return $message;
    }
}
