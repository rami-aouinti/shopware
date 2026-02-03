<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel\DataProvider;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class ProductDataProviderException extends Exception implements JsonApiErrorSerializable
{
    public function __construct(private readonly JsonApiError $jsonApiError)
    {
        parent::__construct($jsonApiError->getDetail());
    }

    public static function currencyShortNameMissing(string $currencyIsoCode, string $currencyId, string $languageId): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Kurzname der Währung fehlt',
                'en' => 'Currency short name missing',
            ],
            'detail' => [
                'de' => sprintf(
                    'Die Währung (ISO-Code "%s") hat keinen Kurznamen in der angegebenen Sprache (ID "%s"). Es können keine Produktdaten für die Barcode-Etiketten-Erstellung bereitgestellt werden.',
                    $currencyIsoCode,
                    $languageId,
                ),
                'en' => sprintf(
                    'Currency (ISO-Code "%s") does not have a short name in the given language (ID "%s"). No product data can be provided for the barcode label creation.',
                    $currencyIsoCode,
                    $languageId,
                ),
            ],
            'meta' => [
                'currencyIsoCode' => $currencyIsoCode,
                'currencyId' => $currencyId,
                'languageId' => $languageId,
            ],
        ]));
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }
}
