<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Adapter;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;

class SwissPostAdapterException extends CarrierAdapterException
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_SWISS_POST__ADAPTER__';
    private const ERROR_CODE_SHIPMENT_BLUEPRINT_HAS_NO_PARCELS = self::ERROR_CODE_NAMESPACE . 'SHIPMENT_BLUEPRINT_HAS_NO_PARCELS';
    private const ERROR_CODE_PARCEL_TOTAL_WEIGHT_IS_UNDEFINED = self::ERROR_CODE_NAMESPACE . 'PARCEL_TOTAL_WEIGHT_IS_UNDEFINED';
    private const ERROR_CODE_NO_PRODUCT_CODE = self::ERROR_CODE_NAMESPACE . 'NO_PRODUCT_CODE';
    private const ERROR_CODE_DELIVERY_DATE = self::ERROR_CODE_NAMESPACE . 'NO_DELIVERY_DATE';
    private const ERROR_CODE_UNSUPPORTED_PAGE_FORMAT = self::ERROR_CODE_NAMESPACE . 'UNSUPPORTED_PAGE_FORMAT';

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

    public static function parcelTotalWeightIsUndefined(int $parcelNumber): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_PARCEL_TOTAL_WEIGHT_IS_UNDEFINED,
            'title' => [
                'de' => 'Paketgewicht kann nicht bestimmt werden',
                'en' => 'Parcel weight cannot be determined',
            ],
            'detail' => [
                'de' => sprintf(
                    'Das Paket %d enthält mindestens einen Artikel mit einem nicht definierten Gewicht. ' .
                    'Hinterlege ein Gewicht für jeden Artikel oder setze das Gesamtgewicht des Pakets manuell.',
                    $parcelNumber,
                ),
                'en' => sprintf(
                    'The parcel %d has at least one item with an undefined weight. Set a weight for each item or ' .
                    'overwrite the total weight manually.',
                    $parcelNumber,
                ),
            ],
            'meta' => [
                'parcelNumber' => $parcelNumber,
            ],
        ]));
    }

    public static function noProductSpecified(): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_NO_PRODUCT_CODE,
            'title' => [
                'de' => 'Kein Produkt ausgewählt',
                'en' => 'No product specified',
            ],
            'detail' => [
                'de' => 'Es wurde kein Produkt angegeben.',
                'en' => 'No product was specified.',
            ],
        ]));
    }

    public static function noDeliveryDateSpecified(): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_DELIVERY_DATE,
            'title' => [
                'de' => 'Kein Zustelldatum ausgewählt',
                'en' => 'No delivery date specified',
            ],
            'detail' => [
                'de' => 'Es wurde kein Zustelldatum angegeben.',
                'en' => 'No delivery date was specified.',
            ],
        ]));
    }
}
