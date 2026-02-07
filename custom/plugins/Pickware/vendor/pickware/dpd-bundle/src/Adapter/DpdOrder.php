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

use Pickware\DpdBundle\Api\DpdProduct;
use Pickware\DpdBundle\Api\Services\ShipmentServiceOption;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Shipment\Address;

class DpdOrder
{
    private string $sendingDepotId;
    private Address $recipientAddress;
    private Address $senderAddress;
    private Parcel $parcel;
    private DpdProduct $product;
    private string $identificationNumber;
    private string $senderCustomerNumber;

    /** @var ShipmentServiceOption[] $shipmentServices */
    private array $shipmentServices = [];

    private bool $isReturnShipment = false;

    public function toArray(): array
    {
        $dpdOrder = [
            'parcels' => $this->encodeParcelInformation($this->parcel),
            'generalShipmentData' => [
                'identificationNumber' => $this->identificationNumber,
                'sendingDepot' => $this->sendingDepotId,
                'product' => $this->product->getCode(),
                'sender' => [
                    ...self::getAddressAsAddressArray($this->senderAddress),
                    'customerNumber' => $this->senderCustomerNumber,
                ],
                'recipient' => self::getAddressAsAddressArray($this->recipientAddress),
            ],
            'productAndServiceData' => ['orderType' => 'consignment'],
        ];

        foreach ($this->shipmentServices as $service) {
            $service->applyToShipmentArray($dpdOrder);
        }

        return $dpdOrder;
    }

    private function encodeParcelInformation(Parcel $parcel): array
    {
        $dimensions = $parcel->getDimensions();

        if (
            $dimensions
            && $dimensions->getLength()->convertTo('cm') >= 1.0
            && $dimensions->getWidth()->convertTo('cm') >= 1.0
            && $dimensions->getHeight()->convertTo('cm') >= 1.0
        ) {
            $volume = sprintf(
                '%s%s%s',
                // DPD requires volume measures in the format LLLWWWHHH in centimeters
                str_pad((string)(int) $dimensions->getLength()->convertTo('cm'), 3, '0', STR_PAD_LEFT),
                str_pad((string)(int) $dimensions->getWidth()->convertTo('cm'), 3, '0', STR_PAD_LEFT),
                str_pad((string)(int) $dimensions->getHeight()->convertTo('cm'), 3, '0', STR_PAD_LEFT),
            );
        }

        $weight = $parcel->getTotalWeight();
        if ($weight === null) {
            throw DpdAdapterException::parcelTotalWeightIsUndefined();
        }

        return array_filter([
            // DPD requires weight measures in 10 gram steps without decimal points
            'weight' => (int) ($weight->convertTo('g') / 10),
            'volume' => $volume ?? null,
            'customerReferenceNumber1' => $parcel->getCustomerReference(),
            'returns' => $this->isReturnShipment ? true : null,
        ], fn($value) => $value !== null);
    }

    /**
     * @return string[]
     */
    private static function getAddressAsAddressArray(Address $address): array
    {
        $nameArray = $address->getOptimizedNameArray(['name1', 'name2', 'contact']);

        return array_filter([
            ...$nameArray,
            'street' => $address->getStreet(),
            'houseNo' => $address->getHouseNumber(),
            'country' => $address->getCountryIso(),
            // DPD uses only the last two letters of the ISO 3166-2 codes (i.e. BB from DE-BB)
            'state' => $address->getStateIso() ? mb_substr($address->getStateIso(), -2) : null,
            'zipCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'phone' => $address->getPhone(),
            'email' => $address->getEmail(),
        ], fn($value) => $value !== null && $value !== '');
    }

    public function setIdentificationNumber(string $identificationNumber): void
    {
        $this->identificationNumber = $identificationNumber;
    }

    public function getSendingDepotId(): string
    {
        return $this->sendingDepotId;
    }

    public function setSendingDepotId(string $sendingDepotId): void
    {
        $this->sendingDepotId = $sendingDepotId;
    }

    public function setRecipientAddress(Address $recipientAddress): void
    {
        $this->recipientAddress = $recipientAddress;
    }

    public function setSenderAddress(Address $senderAddress): void
    {
        $this->senderAddress = $senderAddress;
    }

    public function getParcel(): Parcel
    {
        return $this->parcel;
    }

    public function setParcel(Parcel $parcel): void
    {
        $this->parcel = $parcel;
    }

    public function setProduct(DpdProduct $product): void
    {
        $this->product = $product;
    }

    public function getRecipientAddress(): Address
    {
        return $this->recipientAddress;
    }

    public function getSenderAddress(): Address
    {
        return $this->senderAddress;
    }

    public function getProduct(): DpdProduct
    {
        return $this->product;
    }

    public function getSenderCustomerNumber(): string
    {
        return $this->senderCustomerNumber;
    }

    public function setSenderCustomerNumber(string $senderCustomerNumber): void
    {
        $this->senderCustomerNumber = $senderCustomerNumber;
    }

    /**
     * @param ShipmentServiceOption[] $shipmentServiceOptions
     */
    public function setShipmentServiceOptions(array $shipmentServiceOptions): void
    {
        $this->shipmentServices = $shipmentServiceOptions;
    }

    /**
     * @return ShipmentServiceOption[]
     */
    public function getServiceOptions(): array
    {
        return $this->shipmentServices;
    }

    public function setIsReturnShipment(bool $isReturnShipment): void
    {
        $this->isReturnShipment = $isReturnShipment;
    }

    public function isReturnShipment(): bool
    {
        return $this->isReturnShipment;
    }
}
