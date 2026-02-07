<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api;

use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\ShippingBundle\Shipment\ShipmentType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostShipment
{
    public function __construct(
        private readonly Address $receiverAddress,
        private readonly Address $senderAddress,
        /* @var Parcel[] */
        private readonly array $parcels,
        private readonly AustrianPostProduct $product,
        private readonly array $shipmentServices = [],
        private readonly ?string $deliveryInstruction = null,
        private readonly ?string $customerReference = null,
        private readonly ?string $invoiceNumber = null,
        private readonly ?ShipmentType $typeOfShipment = null,
        private readonly bool $createExportDeclaration = false,
        private readonly ?string $movementReferenceNumber = null,
    ) {}

    public function toArray(): array
    {
        $shipmentArray = [
            'ColloList' => ['ColloRow' => $this->createColloRowsFromParcels($this->parcels)],
            'DeliveryServiceThirdPartyID' => $this->product->getThirdPartyId(),
            'OURecipientAddress' => self::convertAddressToAustrianPostFormat($this->receiverAddress),
            'OUShipperAddress' => self::convertAddressToAustrianPostFormat($this->senderAddress),
            'OUShipperReference1' => $this->customerReference,
            'ReturnOptionID' => '1',
            'CustomDataBit2' => '1',
            'DeliveryInstruction' => $this->deliveryInstruction,
            'ShipmentDocumentEntryList' => [
                'ShipmentDocumentEntry' => [
                    [
                        'DocumentID' => 3, //Invoice
                        'Quantity' => 1,
                        'Number' => $this->invoiceNumber,
                    ],
                ],
            ],
        ];

        if ($this->movementReferenceNumber) {
            $shipmentArray['MovementReferenceNumber'] = $this->movementReferenceNumber;
        }

        foreach ($this->shipmentServices as $service) {
            $service->applyToShipmentArray($shipmentArray);
        }

        if ($this->createExportDeclaration) {
            $shipmentArray['BusinessDocumentEntryList'] = [
                'CustomsDeclaration',
                'ParcelRegistrationCard',
            ];
        }

        return $shipmentArray;
    }

    private function createColloRowsFromParcels(array $parcels): array
    {
        $colloRows = [];
        /** @var Parcel $parcel */
        foreach ($parcels as $parcel) {
            $itemRows = [];
            foreach ($parcel->getItems() as $item) {
                $itemRow = [];
                $itemRow['ArticleName'] = $item->getName();
                $itemRow['ConsumerUnitNetWeight'] = $item->getUnitWeight()?->convertTo('kg');
                $itemRow['CountryOfOriginID'] = mb_strtoupper($item->getCountryOfOrigin()?->getIso2Code() ?? '');
                $itemRow['CurrencyID'] = $item->getUnitPrice()?->getCurrency()->getIsoCode();
                $itemRow['CustomsOptionID'] = match ($this->typeOfShipment) {
                    ShipmentType::SaleOfGoods => 1,
                    ShipmentType::Gift => 2,
                    ShipmentType::Documents => 3,
                    ShipmentType::CommercialSample => 4,
                    ShipmentType::ReturnedGoods => 5,
                    ShipmentType::Other => 6,
                    default => null,
                };
                $itemRow['HSTariffNumber'] = $item->getTariffNumber();
                $itemRow['Quantity'] = $item->getQuantity();
                $itemRow['UnitID'] = 'KGM';
                $itemRow['ValueOfGoodsPerUnit'] = $item->getUnitPrice()?->getValue();

                $itemRows[] = array_filter($itemRow, fn($value) => $value !== null && $value !== '');
            }

            $colloRow = [];
            $colloRow['ColloArticleList'] = ['ColloArticleRow' => $itemRows];
            $colloRow['Weight'] = $parcel->getTotalWeight()?->convertTo('kg');
            $colloRow['Length'] = $parcel->getDimensions()?->getLength()->convertTo('cm');
            $colloRow['Width'] = $parcel->getDimensions()?->getWidth()->convertTo('cm');
            $colloRow['Height'] = $parcel->getDimensions()?->getHeight()->convertTo('cm');

            $colloRows[] = array_filter($colloRow, fn($value) => $value !== null && $value !== '');
        }

        return $colloRows;
    }

    private static function convertAddressToAustrianPostFormat(Address $address): array
    {
        $nameArray = $address->getOptimizedNameArray(['Name1', 'Name2', 'Name3']);

        return array_filter([
            ...$nameArray,
            'AddressLine1' => $address->getStreet(),
            'HouseNumber' => $address->getHouseNumber(),
            'CountryID' => $address->getCountryIso(),
            'PostalCode' => $address->getZipCode(),
            'City' => $address->getCity(),
            'Tel1' => $address->getPhone(),
            'Email' => $address->getEmail(),
        ], fn($value) => $value !== null && $value !== '');
    }

    public function getParcels(): array
    {
        return $this->parcels;
    }

    public function getProduct(): AustrianPostProduct
    {
        return $this->product;
    }

    public function getShipmentServices(): array
    {
        return $this->shipmentServices;
    }

    public function getCustomerReference(): ?string
    {
        return $this->customerReference;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function getTypeOfShipment(): ?ShipmentType
    {
        return $this->typeOfShipment;
    }

    public function getReceiverAddress(): Address
    {
        return $this->receiverAddress;
    }

    public function getSenderAddress(): Address
    {
        return $this->senderAddress;
    }

    public function shouldCreateExportDeclaration(): bool
    {
        return $this->createExportDeclaration;
    }

    public function getDeliveryInstruction(): ?string
    {
        return $this->deliveryInstruction;
    }
}
