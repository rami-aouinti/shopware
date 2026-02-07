<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Adapter;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;
use Throwable;

class UpsAdapterException extends CarrierAdapterException
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_UPS__ADAPTER__';
    private const ERROR_CODE_SHIPMENT_BLUEPRINT_HAS_NO_PARCELS = self::ERROR_CODE_NAMESPACE . 'SHIPMENT_BLUEPRINT_HAS_NO_PARCELS';
    private const ERROR_CODE_PARCEL_TOTAL_WEIGHT_IS_UNDEFINED = self::ERROR_CODE_NAMESPACE . 'PARCEL_TOTAL_WEIGHT_IS_UNDEFINED';
    private const ERROR_CODE_PARCEL_HAS_ITEMS_WITH_UNDEFINED_VALUE = self::ERROR_CODE_NAMESPACE . 'PARCEL_HAS_ITEMS_WITH_UNDEFINED_VALUE';
    private const ERROR_CODE_NO_PRODUCT_CODE = self::ERROR_CODE_NAMESPACE . 'NO_PRODUCT_CODE';
    private const ERROR_CODE_NO_PACKAGING_CODE = self::ERROR_CODE_NAMESPACE . 'NO_PACKAGING_CODE';
    private const ERROR_CODE_CANNOT_APPLY_PACKAGE_SERVICE = self::ERROR_CODE_NAMESPACE . 'CANNOT_APPLY_PACKAGE_SERVICE';

    public static function shipmentBlueprintHasNoParcels(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_SHIPMENT_BLUEPRINT_HAS_NO_PARCELS,
            'title' => 'Shipment has no parcels',
            'detail' => 'The shipment has no parcels and therefore a label cannot be created.',
        ]));
    }

    public static function parcelTotalWeightIsUndefined(int $parcelNumber): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_PARCEL_TOTAL_WEIGHT_IS_UNDEFINED,
            'title' => 'Parcel weight cannot be determined',
            'detail' => sprintf(
                'The parcel %d has at least one item with an undefined weight. Set a weight for each item or ' .
                'overwrite the total weight manually.',
                $parcelNumber,
            ),
            'meta' => [
                'parcelNumber' => $parcelNumber,
            ],
        ]));
    }

    public static function parcelHasItemsWithUndefinedValue(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_PARCEL_HAS_ITEMS_WITH_UNDEFINED_VALUE,
            'title' => 'Parcel has items with undefined customs value',
            'detail' => 'The parcel has at least one item with an undefined customs value. Therefore the total value ' .
                'of the parcel cannot be determined.',
        ]));
    }

    public static function cannotApplyPackageService(int $parcelNumber, Throwable $reason): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_CANNOT_APPLY_PACKAGE_SERVICE,
            'title' => 'Services could not be applied to atleast one parcel',
            'detail' => sprintf(
                'The parcel %d has at least one service that could not be applied. Reason: %s',
                $parcelNumber,
                $reason->getMessage(),
            ),
            'meta' => [
                'parcelNumber' => $parcelNumber,
                'reasons' => JsonApiErrors::fromThrowable($reason),
            ],
        ]));
    }

    public static function noProductSpecified(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_NO_PRODUCT_CODE,
            'title' => 'No product specified',
            'detail' => 'No product was specified.',
        ]));
    }

    public static function noPackagingSpecified(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_NO_PACKAGING_CODE,
            'title' => 'No packaging specified',
            'detail' => 'No packaging was specified.',
        ]));
    }

    public static function missingInvoiceNumber(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Fehlende Rechnungsnummer',
                'en' => 'Missing invoice number',
            ],
            'detail' => [
                'de' => 'Für die Sendung wurde keine Rechnungsnummer angegeben. Evtl. hat die Bestellung noch keine Rechnung.',
                'en' => 'No invoice number was specified for the shipment. The order may not yet have an invoice.',
            ],
        ]));
    }

    public static function countryOfOriginMissing(string $itemName): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Position ohne Herkunftsland',
                'en' => 'Item without country of origin',
            ],
            'detail' => [
                'de' => sprintf('Der Sendungsposition %s fehlt das Herkunftsland. Evtl. wurde kein Herkunftsland für den entsprechenden Artikel konfiguriert.', $itemName),
                'en' => sprintf('The shipment item %s is missing the country of origin. No country of origin may have been configured for the corresponding product.', $itemName),
            ],
            'meta' => [
                'itemName' => $itemName,
            ],
        ]));
    }

    public static function tariffNumberMissing(string $itemName): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Sendungsposition ohne Zolltarifnummer',
                'en' => 'Shipment item without customs tariff number',
            ],
            'detail' => [
                'de' => sprintf('Der Sendungsposition %s fehlt die Zolltarifnummer. Evtl. wurde keine Zolltarifnummer für den entsprechenden Artikel konfiguriert.', $itemName),
                'en' => sprintf('The shipment item %s is missing the customs tariff number. No customs tariff number may have been configured for the corresponding product.', $itemName),
            ],
            'meta' => [
                'itemName' => $itemName,
            ],
        ]));
    }

    public static function incotermIsRequiredForCommercialInvoice(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Incoterm für Handelsrechnung erforderlich',
                'en' => 'Incoterm required for commercial invoice',
            ],
            'detail' => [
                'de' => 'Für die Erstellung einer Handelsrechnung ist ein Incoterm erforderlich.',
                'en' => 'An incoterm is required for the creation of a commercial invoice.',
            ],
        ]));
    }
}
