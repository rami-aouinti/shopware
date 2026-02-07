<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Api;

use JsonSerializable;
use Pickware\SendcloudBundle\Adapter\SendcloudAdapterException;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\ShippingBundle\Shipment\ShipmentType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class SendcloudShipment implements JsonSerializable
{
    /**
     * @param Parcel[] $parcels
     */
    public function __construct(
        private readonly Address $receiverAddress,
        private readonly array $parcels,
        private readonly string $shippingMethodTechnicalName,
        private readonly ?ShipmentType $typeOfShipment,
        private readonly ?string $invoiceNumber,
        private readonly ?string $invoiceDate,
        private readonly ?Address $importerOfRecordsAddress,
        private readonly bool $sendExportInformation,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'parcels' => $this->createParcels($this->parcels),
        ];
    }

    /**
     * @param Parcel[] $parcels
     */
    private function createParcels(array $parcels): array
    {
        $packages = [];
        foreach ($parcels as $index => $parcel) {
            if (!$parcel->getTotalWeight()) {
                throw SendcloudAdapterException::noParcelWeight($index + 1);
            }

            $nameArray = $this->receiverAddress->getOptimizedNameArray(['name', 'company', 'additional']);

            $package = [
                'name' => $nameArray['name'],
                'company_name' => $nameArray['company'] ?? '',
                'address' => $this->receiverAddress->getStreet(),
                'address_2' => $nameArray['additional'] ?? '',
                'house_number' => $this->receiverAddress->getHouseNumber(),
                'city' => $this->receiverAddress->getCity(),
                'postal_code' => $this->receiverAddress->getZipCode(),
                'country' => $this->receiverAddress->getCountryIso(),
                'country_state' => $this->receiverAddress->getStateIso(),
                'email' => $this->receiverAddress->getEmail(),
                'telephone' => $this->receiverAddress->getPhone(),
                'shipping_method_checkout_name' => $this->shippingMethodTechnicalName,
                'request_label' => 'true',
                'shipment' => ['id' => 8], // Sendcloud requires the shipment method to be set even if we want to use shipping rules because of this use the "unstamped letter" method to bypass this check
                'apply_shipping_rules' => 'true',
                'weight' => round($parcel->getTotalWeight()->convertTo('kg'), 3), // Sendcloud API allows a maximum of 3 decimal places
                'parcel_items' => $this->createItems($parcel->getItems()),
            ];

            if ($this->sendExportInformation) {
                if ($this->typeOfShipment === null) {
                    throw SendcloudAdapterException::typeOfShipmentMissing();
                }

                if ($this->invoiceNumber === null) {
                    throw SendcloudAdapterException::invoiceMissing();
                }

                $shipmentType = match ($this->typeOfShipment) {
                    ShipmentType::Gift => 0,
                    ShipmentType::Documents => 1,
                    ShipmentType::SaleOfGoods => 2,
                    ShipmentType::CommercialSample => 3,
                    ShipmentType::ReturnedGoods => 4,
                    ShipmentType::Other => throw SendcloudAdapterException::shipmentTypeNotSupported(),
                };

                $package['customs_information']['customs_shipment_type'] = $shipmentType;
                $package['customs_information']['customs_invoice_nr'] = $this->invoiceNumber;
                $package['customs_information']['invoice_date'] = $this->invoiceDate;

                if ($this->importerOfRecordsAddress) {
                    $name = trim(sprintf(
                        '%s %s',
                        $this->importerOfRecordsAddress->getFirstName(),
                        $this->importerOfRecordsAddress->getLastName(),
                    ));
                    $company = trim(
                        sprintf(
                            '%s, %s',
                            $this->importerOfRecordsAddress->getCompany(),
                            $this->importerOfRecordsAddress->getDepartment(),
                        ),
                        " \t\n\r\0\x0B,",
                    );

                    $package['customs_information']['importer_of_record'] = array_filter([
                        'name' => $name,
                        'company_name' => $company,
                        'address_1' => $this->importerOfRecordsAddress->getStreet(),
                        'address_2' => $this->importerOfRecordsAddress->getAddressAddition(),
                        'house_number' => $this->importerOfRecordsAddress->getHouseNumber(),
                        'city' => $this->importerOfRecordsAddress->getCity(),
                        'postal_code' => $this->importerOfRecordsAddress->getZipCode(),
                        'country_code' => $this->importerOfRecordsAddress->getCountryIso(),
                        'email' => $this->importerOfRecordsAddress->getEmail(),
                        'telephone' => $this->importerOfRecordsAddress->getPhone(),
                    ]);

                    $package['customs_information']['tax_numbers'] = [
                        'sender' => [],
                        'receiver' => [],
                        'importer_of_record' => [
                            [
                                'name' => 'VAT',
                                // VAT IDs are always registered in the country where the tax is paid. This can differ
                                // from the country of the importer address since companies of other countries can be
                                // an importer. The receiver country should be in the country where the importer pays
                                // the tax because of this we can assume that the VAT ID is also registered in the
                                // country of the receiver.
                                'country_code' => $this->receiverAddress->getCountryIso(),
                                'value' => $this->importerOfRecordsAddress->getCustomsReference(),
                            ],
                        ],
                    ];
                }
            }

            if ($parcel->getCustomerReference()) {
                $package['order_number'] = $parcel->getCustomerReference();
            }

            if ($parcel->getTotalValue()) {
                $package['total_order_value'] = round($parcel->getTotalValue()->getValue(), 2);
                $package['total_order_value_currency'] = $parcel->getTotalValue()->getCurrency()->getIsoCode();
            }

            if ($parcel->getDimensions()) {
                $package['length'] = $parcel->getDimensions()->getLength()->convertTo('cm');
                $package['width'] = $parcel->getDimensions()->getWidth()->convertTo('cm');
                $package['height'] = $parcel->getDimensions()->getHeight()->convertTo('cm');
            }

            $packages[] = $package;
        }

        return $packages;
    }

    /**
     * @param ParcelItem[] $parcelItems
     */
    private function createItems(array $parcelItems): array
    {
        $items = [];

        foreach ($parcelItems as $parcelItem) {
            $item = [
                'description' => $parcelItem->getName(),
                'quantity' => $parcelItem->getQuantity(),
            ];

            if ($parcelItem->getTariffNumber()) {
                $item['hs_code'] = $parcelItem->getTariffNumber();
            }

            if ($parcelItem->getUnitWeight()) {
                $item['weight'] = round($parcelItem->getUnitWeight()->convertTo('kg'), 3); // Sendcloud API allows a maximum of 3 decimal places
            }

            if ($parcelItem->getUnitPrice()) {
                $item['value'] = round($parcelItem->getUnitPrice()->getValue(), 2);
                $item['currency'] = $parcelItem->getUnitPrice()->getCurrency()->getIsoCode();
            }

            if ($parcelItem->getCountryOfOrigin()) {
                $item['origin_country'] = mb_strtoupper($parcelItem->getCountryOfOrigin()->getIso2Code());
            }

            $items[] = $item;
        }

        return $items;
    }

    public function getReceiverAddress(): Address
    {
        return $this->receiverAddress;
    }

    public function getImporterOfRecordsAddress(): Address
    {
        return $this->importerOfRecordsAddress;
    }

    public function getParcels(): array
    {
        return $this->parcels;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function getInvoiceDate(): string
    {
        return $this->invoiceDate;
    }

    public function getTypeOfShipment(): ShipmentType
    {
        return $this->typeOfShipment;
    }

    public function getShippingMethodTechnicalName(): string
    {
        return $this->shippingMethodTechnicalName;
    }
}
