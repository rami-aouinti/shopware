<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Adapter;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\DhlExpressBundle\DhlExpressException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\ShippingBundle\Shipment\ShipmentType;

class DhlExpressAdapterException extends DhlExpressException
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_DHL_EXPRESS__ADAPTER__';
    private const ERROR_CODE_SHIPMENT_BLUEPRINT_HAS_NO_PARCELS = self::ERROR_CODE_NAMESPACE . 'SHIPMENT_BLUEPRINT_HAS_NO_PARCELS';
    private const ERROR_CODE_CONTACT_INFO_MISSING_INTERNATIONAL_SHIPPING = self::ERROR_CODE_NAMESPACE . 'CONTACT_INFO_MISSING_INTERNATIONAL_SHIPPING';
    private const ERROR_CODE_PARCEL_ITEM_WEIGHT_MISSING = self::ERROR_CODE_NAMESPACE . 'PARCEL_ITEM_WEIGHT_MISSING';
    private const ERROR_CODE_PARCEL_WEIGHT_OR_DIMENSIONS = self::ERROR_CODE_NAMESPACE . 'PARCEL_WEIGHT_OR_DIMENSIONS';
    private const ERROR_CODE_NO_PRODUCT_SPECIFIED = self::ERROR_CODE_NAMESPACE . 'NO_PRODUCT_SPECIFIED';
    private const ERROR_CODE_UNSUPPORTED_TYPE_OF_SHIPMENT = self::ERROR_CODE_NAMESPACE . 'UNSUPPORTED_TYPE_OF_SHIPMENT';
    private const ERROR_CODE_UNSUPPORTED_DIMENSIONS = self::ERROR_CODE_NAMESPACE . 'UNSUPPORTED_DIMENSIONS';
    private const ERROR_CODE_NO_INVOICE_FOUND = self::ERROR_CODE_NAMESPACE . 'NO_INVOICE_FOUND';

    public static function shipmentBlueprintHasNoParcels(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_SHIPMENT_BLUEPRINT_HAS_NO_PARCELS,
            'title' => 'Shipment has no parcels',
            'detail' => 'The shipment has no parcels and therefore a label cannot be created.',
        ]));
    }

    public static function contactInformationNeededForInternationalShipping(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_CONTACT_INFO_MISSING_INTERNATIONAL_SHIPPING,
            'title' => 'Contact information missing',
            'detail' => 'For international shipping at least one of phone or email is required.',
        ]));
    }

    public static function noParcelWeightOrDimensions(int $parcelNumber): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_PARCEL_WEIGHT_OR_DIMENSIONS,
            'title' => 'No parcel weight or dimension',
            'detail' => sprintf(
                'No parcel weight or dimension for parcel %d configured.',
                $parcelNumber,
            ),
            'meta' => [
                'parcelNumber' => $parcelNumber,
            ],
        ]));
    }

    public static function cannotDetermineShipmentValue(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Can not determine shipment value',
                'de' => 'Wert der Sendung nicht bestimmt verfügbar',
            ],
            'detail' => [
                'en' => 'Can not determine value of shipment.',
                'de' => 'Der Wert der Sendung kann nicht bestimmt werden',
            ],
        ]));
    }

    public static function noItemWeight(string $itemName): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_PARCEL_ITEM_WEIGHT_MISSING,
            'title' => 'Parcel item weight missing',
            'detail' => sprintf(
                'No parcel item weight defined for item %s.',
                $itemName,
            ),
            'meta' => [
                'itemName' => $itemName,
            ],
        ]));
    }

    public static function noProductSpecified(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_NO_PRODUCT_SPECIFIED,
            'title' => 'No product specified',
            'detail' => 'No product was specified.',
        ]));
    }

    public static function unsupportedShipmentType(?ShipmentType $shipmentType): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_UNSUPPORTED_TYPE_OF_SHIPMENT,
            'title' => 'Unsupported type of shipment',
            'detail' => sprintf(
                'Currently only "Sale of goods" and "Gift" are supported types of shipment. Used: %s',
                $shipmentType->value ?? 'None',
            ),
            'meta' => [
                'shipmentType' => $shipmentType,
            ],
        ]));
    }

    public static function parcelDimensionsUnsupported(int $parcelNumber): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_UNSUPPORTED_DIMENSIONS,
            'title' => 'Unsupported parcel dimensions',
            'detail' => sprintf(
                'The given dimensions for parcel %d are not supported by DHL Express. The parcel needs to ' .
                'have a dimension of at least 1x1x1cm',
                $parcelNumber,
            ),
            'meta' => [
                'parcelNumber' => $parcelNumber,
            ],
        ]));
    }

    public static function invoiceNeededForExportDocuments(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_NO_INVOICE_FOUND,
            'title' => 'No invoice found',
            'detail' => 'For export documents an invoice is required',
        ]));
    }
}
