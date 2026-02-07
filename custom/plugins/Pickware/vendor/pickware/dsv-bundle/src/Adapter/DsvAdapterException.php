<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Adapter;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\DsvBundle\Api\DsvApiClientException;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;

class DsvAdapterException extends CarrierAdapterException
{
    public static function noParcelWeight(int $parcelNumber): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'No parcel weight configured',
                'de' => 'Kein Paketgewicht konfiguriert',

            ],
            'detail' => [
                'en' => sprintf(
                    'No parcel weight for parcel %d configured.',
                    $parcelNumber,
                ),
                'de' => sprintf(
                    'Kein Paketgewicht für Paket %d konfiguriert.',
                    $parcelNumber,
                ),
            ],
            'meta' => [
                'parcelNumber' => $parcelNumber,
            ],
        ]));
    }

    public static function documentDownloadFailed(DsvApiClientException $exception): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Document download failed',
                'de' => 'Dokument-Download fehlgeschlagen',
            ],
            'detail' => [
                'en' => sprintf('Failed to download document from DSV API. %s', $exception->getMessage()),
                'de' => sprintf('Dokument konnte nicht von der DSV API heruntergeladen werden. %s', $exception->getMessage()),
            ],
            'meta' => [
                'errorMessage' => $exception->getMessage(),
            ],
        ]), $exception);
    }

    public static function parcelWeightTooLow(int $parcelNumber): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Parcel weight too low',
                'de' => 'Paketgewicht zu gering',
            ],
            'detail' => [
                'en' => sprintf(
                    'The weight of parcel %d is too low. The minimum weight is 1 kg.',
                    $parcelNumber,
                ),
                'de' => sprintf(
                    'Das Gewicht des Pakets %d ist zu gering. Das Mindestgewicht beträgt 1 kg.',
                    $parcelNumber,
                ),
            ],
            'meta' => [
                'parcelNumber' => $parcelNumber,
            ],
        ]));
    }
}
