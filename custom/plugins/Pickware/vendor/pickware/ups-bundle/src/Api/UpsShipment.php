<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api;

use JsonSerializable;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\UpsBundle\Adapter\UpsAdapterException;
use Pickware\UpsBundle\Api\Services\AbstractPackageService;
use Pickware\UpsBundle\Api\Services\AbstractShipmentService;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @phpstan-type SerializedPackage array{
 *      Packaging: array{
 *          Code: string,
 *      },
 *      PackageWeight: array{
 *          Weight: numeric-string,
 *          UnitOfMeasurement: array{
 *              Code: 'KGS',
 *          },
 *      },
 *      Description?: 'Customer return',
 *      Dimensions?: array{
 *          Length: numeric-string,
 *          Width: numeric-string,
 *          Height: numeric-string,
 *          UnitOfMeasurement: array{
 *              Code: '00', // Metric
 *          },
 *      },
 *      PackageServiceOptions?: array{DeclaredValue?: array{CurrencyCode?: string, MonetaryValue?: string}},
 *      AdditionalHandlingIndicator?: '',
 * }
 */
#[Exclude]
class UpsShipment implements JsonSerializable
{
    private const PRODUCTS_REQUIRING_ATTENTION_NAME = [
        '01', // Next Day Air
        '54', // Express Plus
    ];

    private bool $isReturnShipment = false;

    /**
     * @var AbstractPackageService[]
     */
    private array $packageServices = [];

    /**
     * @var AbstractShipmentService[]
     */
    private array $shipmentServices = [];

    /**
     * @param Parcel[] $parcels
     */
    public function __construct(
        private readonly Address $senderAddress,
        private readonly Address $receiverAddress,
        private readonly array $parcels,
        private readonly string $product,
        private readonly string $shipperNumber,
        private readonly string $packaging,
        private readonly string $customerReference = '',
        private readonly ?string $movementReferenceNumber = null,
    ) {}

    public function setShipmentServices(array $services): void
    {
        $this->shipmentServices = $services;
    }

    public function setPackageServices(array $services): void
    {
        $this->packageServices = $services;
    }

    public function enableReturnShipment(): void
    {
        $this->isReturnShipment = true;
    }

    public function jsonSerialize(): array
    {
        $shipment = [
            'ShipmentRequest' => [
                'Shipment' => [
                    'Description' => 'Shipment Description',
                    'ReferenceNumber' => [
                        [
                            'Code' => 'ON',
                            'Value' => $this->customerReference,
                        ],
                    ],
                    'PaymentInformation' => [
                        'ShipmentCharge' => [
                            'Type' => '01',
                            'BillShipper' => [
                                'AccountNumber' => $this->shipperNumber,
                            ],
                        ],
                    ],
                    'Service' => [
                        'Code' => $this->product,
                    ],
                    'Package' => $this->createPackages($this->parcels),
                    'NumOfPiecesInShipment' => (string) count($this->parcels),
                ],
                'LabelSpecification' => [
                    'LabelImageFormat' => [
                        'Code' => 'GIF',
                    ],
                ],
            ],
        ];

        if ($this->movementReferenceNumber) {
            $shipment['ShipmentRequest']['Shipment']['MovementReferenceNumber'] = $this->movementReferenceNumber;
        }

        if ($this->senderAddress->getCustomsReference()) {
            $shipment['ShipmentRequest']['Shipment']['GlobalTaxInformation']['AgentTaxIdentificationNumber'][] = [
                'AgentRole' => '20',
                'TaxIdentificationNumber' => [
                    'IdentificationNumber' => $this->senderAddress->getCustomsReference(),
                    'IDNumberCustomerRole' => '06',
                    'IDNumberEncryptionIndicator' => '0',
                    'IDNumberTypeCode' => '000',
                    'IDNumberIssuingCntryCd' => mb_substr($this->senderAddress->getCustomsReference(), 0, 2),
                    'IDNumberPurposeCode' => '01',
                ],
            ];
        }

        if ($this->receiverAddress->getCustomsReference()) {
            $shipment['ShipmentRequest']['Shipment']['GlobalTaxInformation']['AgentTaxIdentificationNumber'][] = [
                'AgentRole' => '30',
                'TaxIdentificationNumber' => [
                    'IdentificationNumber' => $this->receiverAddress->getCustomsReference(),
                    'IDNumberCustomerRole' => '18',
                    'IDNumberEncryptionIndicator' => '0',
                    'IDNumberTypeCode' => '0000',
                    'IDNumberIssuingCntryCd' => mb_substr($this->receiverAddress->getCustomsReference(), 0, 2),
                    'IDNumberPurposeCode' => '01',
                ],
            ];
        }

        $shipment['ShipmentRequest']['Shipment']['ShipTo'] = $this->convertReceiverAddressToArray($this->receiverAddress);
        if ($this->isReturnShipment) {
            $shipment['ShipmentRequest']['Shipment']['ShipFrom'] = $this->convertReceiverAddressToArray($this->senderAddress);
            $shipment['ShipmentRequest']['Shipment']['Shipper'] = $this->convertShipperAddressToArray($this->receiverAddress);
            $shipment['ShipmentRequest']['Shipment']['ReturnService']['Code'] = '9';
        } else {
            $shipment['ShipmentRequest']['Shipment']['Shipper'] = $this->convertShipperAddressToArray($this->senderAddress);
            $shipment['ShipmentRequest']['Shipment']['ShipFrom'] = $this->convertShipperAddressToArray($this->senderAddress);
            unset($shipment['ShipmentRequest']['Shipment']['ShipFrom']['ShipperNumber']);
            unset($shipment['ShipmentRequest']['Shipment']['ShipFrom']['EMailAddress']);
        }

        if ($this->isAccessPointShipment()) {
            $shipment['ShipmentRequest']['Shipment']['AlternateDeliveryAddress'] = $this->convertReceiverAddressToArray($this->receiverAddress);
            $shipment['ShipmentRequest']['Shipment']['AlternateDeliveryAddress']['UPSAccessPointID'] = $this->receiverAddress->getDepartment();
            $shipment['ShipmentRequest']['Shipment']['ShipmentIndicationType'] = ['Code' => '01'];
        }

        foreach ($this->shipmentServices as $shipmentService) {
            $shipmentService->applyToShipmentArray($shipment);
        }

        return $shipment;
    }

    /**
     * @param array<Parcel> $parcels
     * @return array<SerializedPackage>
     */
    private function createPackages(array $parcels): array
    {
        /** @var array<SerializedPackage> $packages */
        $packages = [];
        foreach ($parcels as $parcelIndex => $parcel) {
            $parcelWeight = $parcel->getTotalWeight();
            if ($parcelWeight === null) {
                throw UpsAdapterException::parcelTotalWeightIsUndefined($parcelIndex + 1);
            }

            /** @var SerializedPackage $package */
            $package = [
                'Packaging' => [
                    'Code' => $this->packaging,
                ],
                'PackageWeight' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'KGS',
                    ],
                    'Weight' => (string) $parcelWeight->convertTo('kg'),
                ],
            ];

            if ($this->isReturnShipment) {
                $package['Description'] = 'Customer return';
            }

            if ($parcel->getDimensions()) {
                $package['Dimensions'] = [
                    'UnitOfMeasurement' => [
                        'Code' => '00', // Metric
                    ],
                    'Length' => (string) $parcel->getDimensions()->getLength()->convertTo('cm'),
                    'Width' => (string) $parcel->getDimensions()->getWidth()->convertTo('cm'),
                    'Height' => (string) $parcel->getDimensions()->getHeight()->convertTo('cm'),
                ];
            }

            foreach ($this->packageServices as $packageService) {
                try {
                    $packageService->applyToPackageArray($package, $parcel);
                } catch (UpsAdapterException $exception) {
                    throw UpsAdapterException::cannotApplyPackageService($parcelIndex + 1, $exception);
                }
            }

            $packages[] = $package;
        }

        return $packages;
    }

    private function convertReceiverAddressToArray(Address $address): array
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

            $shopCountryIso = $this->isReturnShipment() ? $this->receiverAddress->getCountryIso() : $this->senderAddress->getCountryIso();

            if (
                in_array($this->product, self::PRODUCTS_REQUIRING_ATTENTION_NAME, true)
                || $address->getCountryIso() !== $shopCountryIso
            ) {
                $addressArray['AttentionName'] = $nameArray['name'] ?? null;
            }

            // Because no company is set we assume its a residential address
            $addressArray['Address']['ResidentialAddressIndicator'] = '';
        }

        if ($address->getCustomsReference()) {
            $addressArray['TaxIdentificationNumber'] = $address->getCustomsReference();
        }

        return $addressArray;
    }

    private function convertShipperAddressToArray(Address $address): array
    {
        $nameArray = $address->getOptimizedNameArray(['name', 'company', 'additional']);
        $name = $nameArray['name'] ?? null;
        $companyName = $nameArray['company'] ?? $name;

        $addressArray = [
            'ShipperNumber' => $this->shipperNumber,
            'Name' => $companyName,
            'AttentionName' => $name,
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

        if ($address->getCustomsReference()) {
            $addressArray['TaxIdentificationNumber'] = $address->getCustomsReference();
        }

        return $addressArray;
    }

    /**
     * Checks if the department field contains an access point id (format: Uxxxxxxxx with x being digits)
     */
    private function isAccessPointShipment()
    {
        if ($this->isReturnShipment) {
            return false;
        }

        return preg_match('/^U\\d{8}$/i', trim($this->receiverAddress->getDepartment()));
    }

    public function getReceiverAddress(): Address
    {
        return $this->receiverAddress;
    }

    public function getSenderAddress(): Address
    {
        return $this->senderAddress;
    }

    public function getParcels(): array
    {
        return $this->parcels;
    }

    public function getShipperNumber(): string
    {
        return $this->shipperNumber;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function getPackagingType(): string
    {
        return $this->packaging;
    }

    public function getCustomerReference(): string
    {
        return $this->customerReference;
    }

    public function getShipmentServices(): array
    {
        return $this->shipmentServices;
    }

    public function getPackageServices(): array
    {
        return $this->packageServices;
    }

    public function isReturnShipment(): bool
    {
        return $this->isReturnShipment;
    }
}
