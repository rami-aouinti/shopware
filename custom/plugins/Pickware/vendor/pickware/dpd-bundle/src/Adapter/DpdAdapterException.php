<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Adapter;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;

class DpdAdapterException extends CarrierAdapterException
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_DPD__ADAPTER__';
    private const ERROR_CODE_UNDEFINED_PRODUCT_CODE = self::ERROR_CODE_NAMESPACE . 'NO_PRODUCT_CODE';
    private const ERROR_CODE_INVALID_PRODUCT_CODE = self::ERROR_CODE_NAMESPACE . 'INVALID_PRODUCT_CODE';
    private const ERROR_CODE_SHIPMENT_BLUEPRINT_HAS_NO_PARCELS = self::ERROR_CODE_NAMESPACE . 'SHIPMENT_BLUEPRINT_HAS_NO_PARCELS';
    private const ERROR_CODE_PARCEL_TOTAL_WEIGHT_IS_UNDEFINED = self::ERROR_CODE_NAMESPACE . 'PARCEL_TOTAL_WEIGHT_IS_UNDEFINED';
    private const ERROR_CODE_INVALID_SENDING_DEPOT_ID = self::ERROR_CODE_NAMESPACE . 'INVALID_SENDING_DEPOT_ID';

    public static function invalidProductCode(string $productCode): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_INVALID_PRODUCT_CODE,
            'title' => [
                'de' => 'Ungültiges DPD-Produkt angegeben',
                'en' => 'Invalid DPD product specified',
            ],
            'detail' => [
                'de' => sprintf('Der angegebene Wert "%s" ist kein gültiger Code für ein DPD Produkt.', $productCode),
                'en' => sprintf('The specified value "%s" is not a valid code for a DPD product.', $productCode),
            ],
            'meta' => ['productCode' => $productCode],
        ]));
    }

    public static function undefinedProductCode(): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_UNDEFINED_PRODUCT_CODE,
            'title' => [
                'de' => 'Kein DPD-Produkt angegeben',
                'en' => 'No DPD product specified',
            ],
            'detail' => [
                'de' => 'Es wurde kein DPD-Produkt angegeben.',
                'en' => 'No DPD product was specified.',
            ],
        ]));
    }

    public static function shipmentBlueprintHasNoParcels(): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_SHIPMENT_BLUEPRINT_HAS_NO_PARCELS,
            'title' => [
                'de' => 'Sendungsentwurf hat keine Pakete',
                'en' => 'Shipment blueprint has no parcels',
            ],
            'detail' => [
                'de' => 'Die Sendung hat keine Pakete und daher kann kein Label erstellt werden.',
                'en' => 'The shipment has no parcels and therefore a label cannot be created.',
            ],
        ]));
    }

    public static function parcelTotalWeightIsUndefined(): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_PARCEL_TOTAL_WEIGHT_IS_UNDEFINED,
            'title' => [
                'de' => 'Paketgewicht kann nicht bestimmt werden',
                'en' => 'Parcel weight cannot be determined',
            ],
            'detail' => [
                'de' => 'Das Paket enthält mindestens einen Artikel mit einem nicht definierten Gewicht. Hinterlege ' .
                    'ein Gewicht für jeden Artikel oder setze das Gesamtgewicht des Pakets manuell.',
                'en' => 'The parcel has at least one item with an undefined weight. Set a weight for each item or ' .
                    'overwrite the total weight manually.',
            ],
        ]));
    }

    public static function invalidSendingDepotId(): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_INVALID_SENDING_DEPOT_ID,
            'title' => [
                'de' => 'Ungültige Versanddepot-ID',
                'en' => 'Invalid sending depot ID',
            ],
            'detail' => [
                'de' => 'Die Versanddepot-ID muss genau 4 Zeichen lang sein, ist sie zu kurz fülle sie bitte von vorne mit Nullen auf, z.B. "123" => "0123".',
                'en' => 'The sending depot ID must be exactly 4 characters long, if it is too short please prefix it with zeros eg. "123" => "0123".',
            ],
        ]));
    }

    public static function personalDeliveryTypeMissing(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Persönliche Zustellung: Typ fehlt',
                'en' => 'Personal delivery: type missing',
            ],
            'detail' => [
                'de' => 'Für die persönliche Zustellung muss ein gültiger Typ ausgewählt werden.',
                'en' => 'A valid type must be chosen for personal delivery.',
            ],
        ]));
    }

    public static function proactiveNotificationEventsMissing(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Proaktive Benachrichtigung: Events fehlen',
                'en' => 'Proactive notification: events missing',
            ],
            'detail' => [
                'de' => 'Für die proaktive Benachrichtigung müssen gültige Events ausgewählt werden.',
                'en' => 'Valid events must be chosen for proactive notification.',
            ],
        ]));
    }
}
