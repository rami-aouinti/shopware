<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Api;

use JsonSerializable;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\SwissPostBundle\Adapter\ParcelReference;
use Pickware\SwissPostBundle\Api\Services\AbstractShipmentOption;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class SwissPostShipment implements JsonSerializable
{
    /** @var AbstractShipmentOption[] */
    private array $shipmentServiceOptions = [];

    public function __construct(
        private readonly Address $senderAddress,
        private readonly Address $receiverAddress,
        private readonly Parcel $parcel,
        private readonly string $parcelReference,
        private readonly array $productCodes,
        private readonly string $frankingLicense,
        private readonly string $domicilePostOffice,
        private readonly int $totalParcelCount,
        private readonly bool $useTestingWebservice,
    ) {}

    public function jsonSerialize(): array
    {
        $parcelIndex = ParcelReference::fromString($this->parcelReference)->getIndex();

        $shipmentArray = [
            'language' => 'DE',
            'frankingLicense' => $this->frankingLicense,
            'ppFranking' => false,
            'customerSystem' => 'Pickware Schweizerische Post Adapter',
            'customer' => $this->convertShipperAddressToArray($this->senderAddress),
            'item' => [
                'itemID' => $this->parcelReference,
                'recipient' => $this->convertReceiverAddressToArray($this->receiverAddress),
                'attributes' => [
                    'przl' => $this->productCodes,
                    'parcelNo' => $parcelIndex + 1,
                    'parcelTotal' => $this->totalParcelCount,
                    'weight' => (int) $this->parcel->getTotalWeight()->convertTo('g'),
                ],
            ],
            'labelDefinition' => [
                'labelLayout' => 'A6',
                'printAddresses' => 'RECIPIENT_AND_CUSTOMER',
                'imageFileType' => 'PDF',
                'imageResolution' => 300,
                'printPreview' => $this->useTestingWebservice,
            ],
        ];

        foreach ($this->shipmentServiceOptions as $shipmentService) {
            $shipmentService->applyToShipmentArray($shipmentArray);
        }

        return $shipmentArray;
    }

    private function convertReceiverAddressToArray(Address $address): array
    {
        $nameArray = $address->getOptimizedNameArray();

        $addressArray = [
            'name1' => $nameArray[0] ?? '',
            'street' => $address->getStreet(),
            'city' => $address->getCity(),
            'zip' => $address->getZipCode(),
            'country' => $address->getCountryIso(),
        ];

        // The swiss post API does not require any house number and does not accept an empty string
        if ($address->getHouseNumber() !== '') {
            $addressArray['houseNo'] = $address->getHouseNumber();
        }

        if ($nameArray[1] ?? false) {
            $addressArray['name2'] = $nameArray[1];
        }

        if ($nameArray[2] ?? false) {
            $addressArray['name3'] = $nameArray[2];
        }

        if ($address->getPhone()) {
            $addressArray['phone'] = $address->getPhone();
        }

        if ($address->getEmail()) {
            $addressArray['email'] = $address->getEmail();
        }

        return $addressArray;
    }

    private function convertShipperAddressToArray(Address $address): array
    {
        $nameArray = $address->getOptimizedNameArray();

        $addressArray = [
            'name1' => $nameArray[0] ?? '',
            'street' => sprintf('%s %s', $address->getStreet(), $address->getHouseNumber()),
            'zip' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountryIso(),
            'domicilePostOffice' => $this->domicilePostOffice,
        ];

        if ($nameArray[1] ?? false) {
            $addressArray['name2'] = $nameArray[1];
        }

        return $addressArray;
    }

    public function setShipmentServiceOptions(array $shipmentServiceOptions): void
    {
        $this->shipmentServiceOptions = $shipmentServiceOptions;
    }
}
