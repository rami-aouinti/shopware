<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api\Services;

use DateTime;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\ShippingBundle\Shipment\FeeType;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Pickware\ShippingBundle\Shipment\ShipmentType;
use Pickware\UpsBundle\Adapter\UpsAdapterException;

class ShipmentServiceOption extends AbstractShipmentService
{
    public function __construct(
        private readonly string $serviceName,
        private array|string $serviceValue = [],
    ) {}

    public static function cashOnDelivery(string $currency, string $codAmount): self
    {
        return new self('COD', array_filter(
            [
                'CODFundsCode' => '1',
                'CODAmount' => [
                    'CurrencyCode' => $currency,
                    'MonetaryValue' => $codAmount,
                ],
            ],
        ));
    }

    public static function saturdayDelivery(): self
    {
        return new self('SaturdayDeliveryIndicator');
    }

    public static function dispatchNotificationOption(string $emailAddress, string $customMessage = ''): self
    {
        if ($customMessage) {
            $email = [
                'EMailAddress' => $emailAddress,
                'Memo' => $customMessage,
            ];
        } else {
            $email = ['EMailAddress' => $emailAddress];
        }

        return new self('Notification', array_filter(
            [
                [
                    'NotificationCode' => '6',
                    'EMail' => $email,
                ],
            ],
        ));
    }

    public static function deliveryNotificationOption(string $emailAddress): self
    {
        return new self('Notification', array_filter(
            [
                [
                    'NotificationCode' => '8',
                    'EMail' => ['EMailAddress' => $emailAddress],
                ],
            ],
        ));
    }

    public static function internationalForms(
        ShipmentBlueprint $shipmentBlueprint,
        string $incoterm,
    ): self {
        if (!$shipmentBlueprint->getInvoiceNumber()) {
            throw UpsAdapterException::missingInvoiceNumber();
        }

        // The upper case strings are special strings that are recognized by UPS.
        $exportReason = match ($shipmentBlueprint->getTypeOfShipment()) {
            ShipmentType::SaleOfGoods => 'SALE',
            ShipmentType::ReturnedGoods => 'RETURN',
            ShipmentType::Gift => 'GIFT',
            ShipmentType::CommercialSample => 'SAMPLE',
            ShipmentType::Documents => 'Documents',
            ShipmentType::Other => $shipmentBlueprint->getExplanationIfTypeOfShipmentIsOther(),
        };

        return new self('InternationalForms', array_filter(
            [
                [
                    'FormType' => '01',
                    'Contacts' => [
                        'SoldTo' => self::convertToAddressArray($shipmentBlueprint->getReceiverAddress()),
                    ],
                    'Product' => self::createProducts($shipmentBlueprint->getItemsOfAllParcelsGroupByAttributes()),
                    'InvoiceNumber' => $shipmentBlueprint->getInvoiceNumber(),
                    'InvoiceDate' => (new DateTime($shipmentBlueprint->getInvoiceDate()))->format('Ymd'),
                    'PurchaseOrderNumber' => $shipmentBlueprint->getCustomerReference(),
                    'TermsOfShipment' => $incoterm,
                    'ReasonForExport' => $exportReason,
                    'Comments' => $shipmentBlueprint->getComment(),
                    'FreightCharges' => [
                        'MonetaryValue' => (string) ($shipmentBlueprint->getTotalFeesOfType(FeeType::ShippingCosts)?->getValue() ?? 0),
                    ],
                    'InsuranceCharges' => [
                        'MonetaryValue' => (string) ($shipmentBlueprint->getTotalFeesOfType(FeeType::Insurance)?->getValue() ?? 0),
                    ],
                    'CurrencyCode' => $shipmentBlueprint->getTotalValue()?->getCurrency()->getIsoCode(),
                    'PointOfOrigin' => $shipmentBlueprint->getOfficeOfOrigin(),
                ],
            ],
        ));
    }

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipment = &$shipmentArray['ShipmentRequest']['Shipment'];

        if (empty($this->serviceValue)) {
            $this->serviceValue = '';
        }

        if (!isset($shipment['ShipmentServiceOptions'])) {
            $shipment['ShipmentServiceOptions'] = [];
        }
        if (isset($shipment['ShipmentServiceOptions'][$this->serviceName])) {
            $serviceOption = $shipment['ShipmentServiceOptions'][$this->serviceName];
            $this->serviceValue = array_merge($serviceOption, $this->serviceValue);
        }

        $shipment['ShipmentServiceOptions'][$this->serviceName] = $this->serviceValue;
    }

    /**
     * @param $items ParcelItem[]
     */
    private static function createProducts(array $items): array
    {
        $products = [];
        foreach ($items as $item) {
            if (!$item->getCountryOfOrigin()) {
                throw UpsAdapterException::countryOfOriginMissing($item->getName());
            }

            if (!$item->getTariffNumber()) {
                throw UpsAdapterException::tariffNumberMissing($item->getName());
            }

            $products[] = [
                'Description' => $item->getCustomsDescription(),
                'Unit' => [
                    'Number' => (string) $item->getQuantity(),
                    'UnitOfMeasurement' => ['Code' => 'PCS'],
                    'Value' => (string) round($item->getUnitPrice()?->getValue(), 6),
                ],
                'CommodityCode' => $item->getTariffNumber(),
                'OriginCountryCode' => $item->getCountryOfOrigin()->getIso2Code(),
                'ProductWeight' => [
                    'UnitOfMeasurement' => ['Code' => 'KGS'],
                    'Value' => $item->getUnitWeight()?->convertTo('kg'),
                ],
            ];
        }

        return $products;
    }

    private static function convertToAddressArray(Address $address): array
    {
        $nameArray = $address->getOptimizedNameArray(['name', 'company', 'additional']);

        $addressArray = [
            'Address' => [
                'AddressLine' => [
                    sprintf('%s %s', $address->getStreet(), $address->getHouseNumber()),
                    $address->getAddressAddition(),
                ],
                'City' => $address->getCity(),
                'StateProvinceCode' => mb_substr($address->getStateIso(), 3),
                'PostalCode' => $address->getZipCode(),
                'CountryCode' => $address->getCountryIso(),
            ],
        ];

        if ($address->getPhone()) {
            $addressArray['Phone'] = [
                'Number' => $address->getPhone(),
            ];
        }

        if ($address->getEmail()) {
            $addressArray['EMailAddress'] = $address->getEmail();
        }

        if ($address->getCompany()) {
            $addressArray['Name'] = $address->getCompany();
            $addressArray['AttentionName'] = sprintf('%s %s', $address->getFirstName(), $address->getLastName());
        } else {
            $addressArray['Name'] = $nameArray['name'] ?? null;
            $addressArray['AttentionName'] = $nameArray['name'] ?? null;
        }

        if ($address->getCustomsReference()) {
            $addressArray['TaxIdentificationNumber'] = $address->getCustomsReference();
        }

        return $addressArray;
    }
}
