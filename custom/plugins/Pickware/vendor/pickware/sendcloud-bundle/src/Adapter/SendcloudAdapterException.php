<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Adapter;

use JsonException;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\SendcloudBundle\Api\SendcloudApiClientException;
use Pickware\SendcloudBundle\SendcloudException;
use Psr\Http\Message\ResponseInterface;

class SendcloudAdapterException extends SendcloudException
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

    public static function noCheckoutDeliveryMethodNameSet(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'No checkout delivery method set',
                'de' => 'Keine Checkout Versandmethode gesetzt',
            ],
            'detail' => [
                'en' => 'No checkout delivery method set.',
                'de' => 'Keine Checkout Versandmethode gesetzt.',
            ],
        ]));
    }

    public static function fromShipmentResponse(ResponseInterface $response): self
    {
        try {
            $status = Json::decodeToArray((string) $response->getBody());
        } catch (JsonException) {
            return self::parcelStatusRetrievalFailed();
        }

        $errorMessages = self::flatErrors($status['parcel']['errors']);

        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Parcel status error',
                'de' => 'Paketstatus-Fehler',
            ],
            'detail' => implode(' ', $errorMessages),
        ]));
    }

    public static function documentDownloadFailed(SendcloudApiClientException $exception): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Document download failed',
                'de' => 'Dokument-Download fehlgeschlagen',
            ],
            'detail' => [
                'en' => 'Failed to download document from Sendcloud API.',
                'de' => 'Dokument konnte nicht von der Sendcloud API heruntergeladen werden.',
            ],
            'meta' => [
                'sendcloudApiClientException' => $exception->serializeToJsonApiError(),
            ],
        ]));
    }

    public static function failedToCancelParcels(array $parcelIds): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Failed to cancel parcels',
                'de' => 'Pakete konnten nicht storniert werden',
            ],
            'detail' => [
                'en' => sprintf(
                    'Failed to cancel parcels with the following IDs: %s',
                    implode(', ', $parcelIds),
                ),
                'de' => sprintf(
                    'Pakete mit den folgenden IDs konnten nicht storniert werden: %s',
                    implode(', ', $parcelIds),
                ),
            ],
            'meta' => [
                'parcelIds' => $parcelIds,
            ],
        ]));
    }

    public static function parcelsRolledback(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Parcels rolled back',
                'de' => 'Pakete zurückgerollt',
            ],
            'detail' => [
                'en' => 'Parcels were rolled back.',
                'de' => 'Pakete wurden zurückgerollt.',
            ],
        ]));
    }

    public static function parcelStatusRetrievalFailed(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Parcel status retrieval failed',
                'de' => 'Paketstatus-Abruf fehlgeschlagen',
            ],
            'detail' => [
                'en' => 'Failed to retrieve parcel status.',
                'de' => 'Paketstatus konnte nicht abgerufen werden.',
            ],
        ]));
    }

    public static function typeOfShipmentMissing(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Type of shipment required',
                'de' => 'Art der Sendung benötigt',
            ],
            'detail' => [
                'en' => 'The type of shipment is required when sending export information. This can be configured ' .
                    'under "Shipping labels common".',
                'de' => 'Die Art der Sendung ist erforderlich, wenn Exportinformationen gesendet werden. Diese kann ' .
                    'unter "Versandetiketten allgemein" hinterlegt werden.',
            ],
        ]));
    }

    public static function invoiceMissing(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Invoice required',
                'de' => 'Rechnung benötigt',
            ],
            'detail' => [
                'en' => 'An invoice is required when sending export information.',
                'de' => 'Eine Rechnung ist erforderlich, wenn Exportinformationen gesendet werden.',
            ],
        ]));
    }

    public static function shipmentTypeNotSupported(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Shipment type not supported',
                'de' => 'Sendungstyp nicht unterstützt',
            ],
            'detail' => [
                'en' => 'The shipment type "other" is not supported.',
                'de' => 'Der Sendungstyp "Sonstiges" wird nicht unterstützt.',
            ],
        ]));
    }
}
