<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use JsonException;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\PhpStandardLibrary\Json\Json;
use function Pickware\PhpStandardLibrary\Language\makeSentence;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;

class DsvApiClientException extends CarrierAdapterException
{
    public static function fromClientException(ClientException $clientException): self
    {
        $message = self::getMessageFromBadResponseException($clientException);

        return new self(new LocalizableJsonApiError([
            'detail' => [
                'en' => sprintf('The DSV API could not process the request: %s', $message),
                'de' => sprintf('Die DSV-API konnte die Anfrage nicht verarbeiten: %s', $message),
            ],
        ]), $clientException);
    }

    public static function fromServerException(ServerException $serverException): self
    {
        $message = self::getMessageFromBadResponseException($serverException);

        return new self(new LocalizableJsonApiError([
            'detail' => [
                'en' => sprintf('The DSV API is currently not available: %s', $message),
                'de' => sprintf('Die DSV-API ist aktuell nicht verfügbar: %s', $message),
            ],
        ]), $serverException);
    }

    private static function getMessageFromBadResponseException(BadResponseException $e): string
    {
        try {
            $json = Json::decodeToArray(json: (string) $e->getResponse()->getBody());
        } catch (JsonException) {
            return makeSentence(sprintf('The following response could not be parsed: %s', (string) $e->getResponse()->getBody()));
        }

        if (array_key_exists('fields', $json)) {
            $errorMessages = [];
            foreach ($json['fields'] as $field) {
                $message = makeSentence($field['message']);
                $errorMessages[] = $message;
            }

            return implode(' ', $errorMessages);
        }

        if (array_key_exists('development', $json)) {
            return makeSentence($json['development']['message']);
        }

        if (array_key_exists('error', $json)) {
            return makeSentence($json['error_description'] ?? $json['error']);
        }

        if (array_key_exists('message', $json)) {
            return makeSentence($json['message']);
        }

        return makeSentence(sprintf('The following response could not be parsed: %s', (string) $e->getResponse()->getBody()));
    }
}
