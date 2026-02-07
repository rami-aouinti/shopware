<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api;

use JsonSerializable;
use Pickware\DsvBundle\Adapter\DsvAdapterException;
use Pickware\DsvBundle\Api\Services\AbstractShipmentServiceOption;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DsvShipment implements JsonSerializable
{
    /**
     * @param Parcel[] $parcels
     */
    public function __construct(
        private readonly Address $senderAddress,
        private readonly Address $receiverAddress,
        private readonly array $parcels,
        private readonly string $customerNumber,
        private readonly DsvProduct $product,
        private readonly DsvPackageType $packageType,
        private readonly ?string $packageDescription = null,
        private readonly ?string $incoterm = null,
        private readonly ?string $placeOfIncoterm = null,
        private readonly ?string $customerReference = null,
        /**
         * @var AbstractShipmentServiceOption[]
         */
        private readonly array $shipmentServices = [],
    ) {}

    public function jsonSerialize(): array
    {
        $dsvShipment = [
            'autobook' => true,
            'parties' => [
                'sender' => $this->createFromAddress($this->senderAddress),
                'receiver' => $this->createFromAddress($this->receiverAddress),
                'freightPayer' => [
                    'address' => [
                        'mdm' => $this->customerNumber,
                    ],
                ],
                'bookingParty' => [
                    'address' => [
                        'mdm' => $this->customerNumber,
                    ],
                ],
            ],
            'product' => [
                'name' => $this->product,
            ],
            'incoterms' => [
                'code' => $this->incoterm,
                'location' => $this->placeOfIncoterm,
            ],
            'packages' => $this->createParcels($this->parcels),
            'references' => [
                [
                    'type' => 'ORDER_NUMBER',
                    'value' => $this->customerReference,
                ],
            ],
            'units' => [
                'dimension' => 'CM',
                'weight' => 'KG',
            ],
        ];

        foreach ($this->shipmentServices as $service) {
            $service->applyToShipmentArray($dsvShipment);
        }

        return $dsvShipment;
    }

    /**
     * @param Parcel[] $parcels
     */
    private function createParcels(array $parcels): array
    {
        $packages = [];
        foreach ($parcels as $index => $parcel) {
            if (!$parcel->getTotalWeight()) {
                throw DsvAdapterException::noParcelWeight($index + 1);
            }

            if ($parcel->getTotalWeight()->isLighterThan(new Weight(1, 'kg'))) {
                throw DsvAdapterException::parcelWeightTooLow($index + 1);
            }

            $packages[] = [
                'quantity' => 1,
                'packageType' => $this->packageType,
                'length' => $parcel->getDimensions()?->getLength()->convertTo('cm'),
                'width' => $parcel->getDimensions()?->getWidth()->convertTo('cm'),
                'height' => $parcel->getDimensions()?->getHeight()->convertTo('cm'),
                'totalWeight' => round($parcel->getTotalWeight()->convertTo('kg'), 0), // DSV API allows no decimal places
                'description' => $this->packageDescription,
                'shippingMarks' => $parcel->getCustomerReference(),
            ];
        }

        return $packages;
    }

    private function createFromAddress(Address $address): array
    {
        $nameArray = $address->getOptimizedNameArray(['name', 'company', 'additional']);

        $addressArray = [
            'companyName' => $nameArray['company'] ?? $nameArray['name'],
            'addressLine1' => $address->getStreet() . ' ' . $address->getHouseNumber(),
            'addressLine2' => $nameArray['additional'] ?? null,
            'city' => $address->getCity(),
            'countryCode' => $address->getCountryIso(),
            'zipCode' => $address->getZipCode(),
            'state' => $address->getStateIso(),
        ];
        $contactArray = [
            'name' => $nameArray['name'],
        ];

        if ($address->getEmail()) {
            $contactArray['email'] = $address->getEmail();
        }

        if ($address->getPhone()) {
            $contactArray['telephone'] = $address->getPhone();
        }

        return [
            'address' => $addressArray,
            'contact' => $contactArray,
        ];
    }

    public function getShipmentServiceOptions(): array
    {
        return $this->shipmentServices;
    }

    public function getSenderAddress(): Address
    {
        return $this->senderAddress;
    }

    public function getReceiverAddress(): Address
    {
        return $this->receiverAddress;
    }

    public function getProduct(): DsvProduct
    {
        return $this->product;
    }

    public function getParcels(): array
    {
        return $this->parcels;
    }

    public function getPlaceOfIncoterm(): ?string
    {
        return $this->placeOfIncoterm;
    }

    public function getCustomerReference(): ?string
    {
        return $this->customerReference;
    }

    public function getIncoterm(): ?string
    {
        return $this->incoterm;
    }

    public function getCustomerNumber(): string
    {
        return $this->customerNumber;
    }

    public function getPackageType(): DsvPackageType
    {
        return $this->packageType;
    }
}
