<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api;

use DateTime;
use JsonSerializable;
use Pickware\DhlExpressBundle\Adapter\DhlExpressAdapterException;
use Pickware\DhlExpressBundle\Api\Services\AbstractShipmentOption;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\ShippingBundle\Shipment\ShipmentType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @phpstan-type DhlExpressAddress array{
 *     postalAddress: array{addressLine1: string, cityName: string, postalCode: string, countryCode: string, addressLine2?: non-falsy-string, provinceCode?: non-falsy-string},
 *     contactInformation: array{companyName: string, fullName: string, phone: string, email?: non-falsy-string},
 *     registrationNumbers?: array{typeCode: string, issuerCountryCode: string, number: string},
 *     typeCode?: 'business'
 * }
 * @phpstan-type Package array{weight: float, dimensions: array{length: float, height: float, width: float}, customerReferences: array{array{value: string}}}
 * @phpstan-type ExportLineItem array{
 *      number: int<1, max>,
 *      description: string|null,
 *      price: float,
 *      quantity: array{value: int, unitOfMeasurement: 'PCS'},
 *      manufacturerCountry: string|null,
 *      weight: array{netValue: float, grossValue: float},
 *      commodityCodes: array{array{typeCode: 'outbound', value: string|null}},
 *      exportReasonType: 'permanent'
 *  }
 * @phpstan-type ExportInformation array{
 *      content: array{
 *          declaredValue: float,
 *          declaredValueCurrency: string,
 *          exportDeclaration: array{
 *              lineItems: list<ExportLineItem>,
 *              invoice: array{number: non-falsy-string, date: non-falsy-string, customerReferences?: array{array{typeCode: 'MRN', value: non-falsy-string}}},
 *          placeOfIncoterm: string|null,
 *          exportReasonType: 'permanent',
 *          shipmentType: 'commercial'|'personal',
 *          additionalCharges?: array{array{typeCode: 'delivery', value: float}}}
 *      },
 *      outputImageProperties: array{
 *          imageOptions: array{array{typeCode: 'invoice', isRequested: true}, array{typeCode: 'waybillDoc', isRequested: true}},
 *          splitTransportAndWaybillDocLabels: true
 *      }
 *  }
 * @phpstan-type DhlExpressShipmentArray array{
 *       plannedShippingDateAndTime: non-falsy-string,
 *       pickup: array{isRequested: false},
 *       productCode: DhlExpressProduct,
 *       accounts: array{array{typeCode: 'shipper', number: string}},
 *       customerDetails: array{shipperDetails: DhlExpressAddress, receiverDetails: DhlExpressAddress, importerDetails?: DhlExpressAddress},
 *       content: array{
 *           packages: list<Package>,
 *           isCustomsDeclarable: bool,
 *           description: string|null,
 *           unitOfMeasurement: 'metric',
 *           incoterm?: non-falsy-string,
 *           declaredValue: float,
 *           declaredValueCurrency: string,
 *           exportDeclaration: array{
 *               lineItems: list<ExportLineItem>,
 *               invoice: array{number: non-falsy-string, date: non-falsy-string, customerReferences?: array{array{typeCode: 'MRN', value: non-falsy-string}}},
 *               placeOfIncoterm: string|null,
 *               exportReasonType: 'permanent',
 *               shipmentType: 'commercial'|'personal',
 *               additionalCharges?: array{array{typeCode: 'delivery', value: float}}
 *          }
 *     },
 *     outputImageProperties: array{
 *         imageOptions: array{array{typeCode: 'invoice', isRequested: true}, array{typeCode: 'waybillDoc', isRequested: true}},
 *         splitTransportAndWaybillDocLabels: true
 *     },
 *     valueAddedServices?: list<array{
 *         serviceCode: string,
 *         value?: float,
 *         currency?: string
 *     }>,
 *     shipmentNotification?: list<array{
 *         typeCode: 'email'|'sms',
 *         receiverId: string,
 *         languageCountryCode: string
 *     }>
 * }
 */
#[Exclude]
class DhlExpressShipment implements JsonSerializable
{
    private bool $isCustomsDeclarable = false;

    /**
     * @var AbstractShipmentOption[]
     */
    private array $shipmentServices = [];

    /**
     * @param Parcel[] $parcels
     * @param ParcelItem[] $items
     */
    public function __construct(
        private readonly Address $senderAddress,
        private readonly Address $receiverAddress,
        private readonly array $parcels,
        private readonly DhlExpressProduct $product,
        private readonly string $shipperNumber,
        private readonly ?string $incoterm = null,
        private readonly ?MoneyValue $totalShipmentValue = null,
        private readonly ?ShipmentType $typeOfShipment = null,
        private readonly ?string $invoiceNumber = null,
        private readonly ?string $invoiceDate = null,
        private readonly ?string $officeOfOrigin = null,
        private readonly ?MoneyValue $shippingCost = null,
        private readonly array $items = [],
        private readonly ?string $movementReferenceNumber = null,
        private readonly ?string $shipmentDescription = null,
        private readonly ?Address $importerOfRecordsAddress = null,
    ) {}

    /**
     * @return DhlExpressShipmentArray
     */
    public function jsonSerialize(): array
    {
        $shipment = [
            // Because the planned shipping date needs to be in the future when creating a label we decided to add
            // 15 minutes to always have a date in the future.
            'plannedShippingDateAndTime' => (new DateTime())->modify('+ 15 minutes')->format('Y-m-d\\TH:i:s\\G\\M\\TP'),
            'pickup' => [
                'isRequested' => false,
            ],
            'productCode' => $this->product,
            'accounts' => [
                [
                    'typeCode' => 'shipper',
                    'number' => $this->shipperNumber,
                ],
            ],
            'customerDetails' => [
                'shipperDetails' => $this->createAddressArrayFromAddress($this->senderAddress),
                'receiverDetails' => $this->createAddressArrayFromAddress($this->receiverAddress),
            ],
            'content' => [
                'packages' => $this->createPackages($this->parcels),
                'isCustomsDeclarable' => $this->isCustomsDeclarable,
                'description' => $this->getShipmentDescription(),
                'unitOfMeasurement' => 'metric',
            ],
        ];

        if ($this->importerOfRecordsAddress) {
            $shipment['customerDetails']['importerDetails'] = $this->createAddressArrayFromAddress($this->importerOfRecordsAddress);

            $shipment['customerDetails']['importerDetails']['registrationNumbers'] = [
                [
                    'typeCode' => 'EOR',
                    'issuerCountryCode' => $this->receiverAddress->getCountryIso(),
                    'number' => $this->importerOfRecordsAddress->getCustomsReference(),
                ],
            ];
            $shipment['customerDetails']['importerDetails']['typeCode'] = 'business';
        }

        if ($this->incoterm) {
            $shipment['content']['incoterm'] = $this->incoterm;
        }

        if ($this->isCustomsDeclarable) {
            $shipment = array_merge_recursive(
                $shipment,
                $this->getExportInformation(),
            );
        }

        foreach ($this->shipmentServices as $service) {
            $service->applyToShipmentArray($shipment);
        }

        return $shipment;
    }

    /**
     * @return DhlExpressAddress
     */
    private function createAddressArrayFromAddress(Address $address): array
    {
        if ($this->isCustomsDeclarable && $address->getEmail() === '' && $address->getPhone() === '') {
            throw DhlExpressAdapterException::contactInformationNeededForInternationalShipping();
        }

        $companyWithDepartment = trim(sprintf('%s %s', $address->getCompany(), $address->getDepartment()));
        $fullName = trim(sprintf('%s %s', $address->getFirstName(), $address->getLastName()));

        $addressArray = [
            'postalAddress' => [
                'addressLine1' => trim(sprintf('%s %s', $address->getStreet(), $address->getHouseNumber())),
                'cityName' => $address->getCity(),
                'postalCode' => $address->getZipCode(),
                'countryCode' => $address->getCountryIso(),
            ],
            'contactInformation' => [
                'companyName' => $companyWithDepartment ?: $fullName,
                'fullName' => $fullName ?: $companyWithDepartment,
                'phone' => $address->getPhone() ?: '-',
            ],
        ];
        if ($address->getAddressAddition()) {
            $addressArray['postalAddress']['addressLine2'] = $address->getAddressAddition();
        }

        if ($address->getStateIso()) {
            $addressArray['postalAddress']['provinceCode'] = $address->getStateIso();
        }

        if ($address->getEmail()) {
            $addressArray['contactInformation']['email'] = $address->getEmail();
        }

        return $addressArray;
    }

    /**
     * @param Parcel[] $parcels
     * @return list<Package>
     */
    private function createPackages(array $parcels): array
    {
        $packages = [];
        foreach ($parcels as $parcelIndex => $parcel) {
            $parcelWeight = $parcel->getTotalWeight();
            $parcelDimensions = $parcel->getDimensions();

            if (!$parcelWeight || !$parcelDimensions) {
                throw DhlExpressAdapterException::noParcelWeightOrDimensions($parcelIndex + 1);
            }

            if (
                $parcelDimensions->getLength()->convertTo('cm') < 1.0
                || $parcelDimensions->getHeight()->convertTo('cm') < 1.0
                || $parcelDimensions->getWidth()->convertTo('cm') < 1.0
            ) {
                throw DhlExpressAdapterException::parcelDimensionsUnsupported($parcelIndex + 1);
            }

            $packages[] = [
                'weight' => round($parcelWeight->convertTo('kg'), 3),
                'dimensions' => [
                    'length' => $parcelDimensions->getLength()->convertTo('cm'),
                    'height' => $parcelDimensions->getHeight()->convertTo('cm'),
                    'width' => $parcelDimensions->getWidth()->convertTo('cm'),
                ],
                'customerReferences' => [
                    [
                        'value' => $parcel->getCustomerReference() ?? '',
                    ],
                ],
            ];
        }

        return $packages;
    }

    /**
     * @return ExportInformation
     */
    private function getExportInformation(): array
    {
        if (!$this->totalShipmentValue) {
            throw DhlExpressAdapterException::cannotDetermineShipmentValue();
        }

        if (
            $this->typeOfShipment !== ShipmentType::SaleOfGoods
            && $this->typeOfShipment !== ShipmentType::Gift
        ) {
            throw DhlExpressAdapterException::unsupportedShipmentType($this->typeOfShipment);
        }

        if (!$this->invoiceNumber) {
            throw DhlExpressAdapterException::invoiceNeededForExportDocuments();
        }

        $shipment = [
            'content' => [],
            'outputImageProperties' => [],
        ];

        $shipment['content']['declaredValue'] = round($this->totalShipmentValue->getValue(), 3);
        $shipment['content']['declaredValueCurrency'] = $this->totalShipmentValue->getCurrency()->getIsoCode();
        $shipment['content']['exportDeclaration'] = [
            'lineItems' => $this->createExportLineItems(),
            'invoice' => [
                'number' => $this->invoiceNumber,
                'date' => (new DateTime($this->invoiceDate))->format('Y-m-d'),
            ],
            'placeOfIncoterm' => $this->officeOfOrigin,
            'exportReasonType' => 'permanent',
            'shipmentType' => $this->typeOfShipment === ShipmentType::SaleOfGoods ? 'commercial' : 'personal',
        ];

        if ($this->movementReferenceNumber) {
            $shipment['content']['exportDeclaration']['invoice']['customerReferences'][] = [
                'typeCode' => 'MRN',
                'value' => $this->movementReferenceNumber,
            ];
        }

        $shipment['outputImageProperties']['imageOptions'] = [
            [
                'typeCode' => 'invoice',
                'isRequested' => true,
            ],
            [
                'typeCode' => 'waybillDoc',
                'isRequested' => true,
            ],
        ];
        $shipment['outputImageProperties']['splitTransportAndWaybillDocLabels'] = true;

        if ($this->shippingCost && $this->shippingCost->getValue() > 0.0) {
            $shipment['content']['exportDeclaration']['additionalCharges'][] = [
                'typeCode' => 'delivery',
                'value' => $this->shippingCost->getValue(),
            ];
        }

        return $shipment;
    }

    /**
     * @return list<ExportLineItem>
     */
    private function createExportLineItems(): array
    {
        $lineItems = [];
        $currentExportLineItemNumber = 1;
        foreach ($this->items as $item) {
            $weight = $item->getTotalWeight();
            if (!$weight) {
                throw DhlExpressAdapterException::noItemWeight($item->getName());
            }

            $lineItems[] = [
                'number' => $currentExportLineItemNumber,
                'description' => $item->getCustomsDescription(),
                'price' => $item->getUnitPrice() ? round($item->getUnitPrice()->getValue(), 3) : 0.0,
                'quantity' => [
                    'value' => $item->getQuantity(),
                    'unitOfMeasurement' => 'PCS',
                ],
                'manufacturerCountry' => $item->getCountryOfOrigin()?->getIso2Code(),
                'weight' => [
                    'netValue' => round($weight->convertTo('kg'), 3),
                    'grossValue' => round($weight->convertTo('kg'), 3),
                ],
                'commodityCodes' => [
                    [
                        'typeCode' => 'outbound',
                        'value' => $item->getTariffNumber(),
                    ],
                ],
                'exportReasonType' => 'permanent',
            ];

            ++$currentExportLineItemNumber;
        }

        return $lineItems;
    }

    public function enableExportDocuments(): void
    {
        $this->isCustomsDeclarable = true;
    }

    /**
     * @param array<AbstractShipmentOption> $services
     */
    public function setShipmentServices(array $services): void
    {
        $this->shipmentServices = $services;
    }

    public function getShipperNumber(): string
    {
        return $this->shipperNumber;
    }

    public function getSenderAddress(): Address
    {
        return $this->senderAddress;
    }

    public function getReceiverAddress(): Address
    {
        return $this->receiverAddress;
    }

    /**
     * @return Parcel[]
     */
    public function getParcels(): array
    {
        return $this->parcels;
    }

    /**
     * @return AbstractShipmentOption[]
     */
    public function getShipmentServices(): array
    {
        return $this->shipmentServices;
    }

    public function getProduct(): DhlExpressProduct
    {
        return $this->product;
    }

    public function getIncoterm(): ?string
    {
        return $this->incoterm;
    }

    public function isCustomsDeclarable(): bool
    {
        return $this->isCustomsDeclarable;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function getInvoiceDate(): string
    {
        return $this->invoiceDate;
    }

    public function getPlaceOfIncoterm(): string
    {
        return $this->officeOfOrigin;
    }

    /**
     * @return non-falsy-string
     */
    public function getShipmentDescription(): string
    {
        return $this->shipmentDescription ?: 'shipment description';
    }
}
