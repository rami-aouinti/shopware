<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Parcel;

use JsonSerializable;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\Shipment\FeeType;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;

/**
 * @deprecated Will be removed in 3.0.0. Use fields on ShipmentBlueprint instead.
 */
class ParcelCustomsInformation implements JsonSerializable
{
    public const SHIPMENT_TYPE_COMMERCIAL_SAMPLE = 'commercial-sample';
    public const SHIPMENT_TYPE_DOCUMENTS = 'documents';
    public const SHIPMENT_TYPE_GIFT = 'gift';
    public const SHIPMENT_TYPE_OTHER = 'other';
    public const SHIPMENT_TYPE_RETURNED_GOODS = 'returned-goods';
    public const SHIPMENT_TYPE_SALE_OF_GOODS = 'sale-of-goods';
    public const FEE_TYPE_SHIPPING_COSTS = 'shipping-costs';

    private Parcel $parcel;
    private ShipmentBlueprint $shipmentBlueprint;

    public function __construct(Parcel $parcel, ?ShipmentBlueprint $shipmentBlueprint)
    {
        $this->parcel = $parcel;
        $this->shipmentBlueprint = $shipmentBlueprint;
        $parcel->setCustomsInformation($this);
    }

    public function jsonSerialize(): array
    {
        $fees = [];
        foreach ($this->shipmentBlueprint->getFees() as $fee) {
            $fees[$fee->getType()->value] = $fee->getAmount();
        }

        return [
            'typeOfShipment' => $this->shipmentBlueprint->getTypeOfShipment()?->value,
            'officeOfOrigin' => $this->shipmentBlueprint->getOfficeOfOrigin(),
            'explanationIfTypeOfShipmentIsOther' => $this->shipmentBlueprint->getExplanationIfTypeOfShipmentIsOther(),
            'invoiceNumbers' => $this->shipmentBlueprint->getInvoiceNumber() == null ? [] : [$this->shipmentBlueprint->getInvoiceNumber()],
            'invoiceNumber' => $this->shipmentBlueprint->getInvoiceNumber(),
            'invoiceDate' => $this->shipmentBlueprint->getInvoiceDate(),
            'permitNumbers' => $this->shipmentBlueprint->getPermitNumbers(),
            'certificateNumbers' => $this->shipmentBlueprint->getCertificateNumbers(),
            'fees' => $fees,
            'comment' => $this->shipmentBlueprint->getComment(),
        ];
    }

    public function getParcel(): Parcel
    {
        return $this->parcel;
    }

    public function getTypeOfShipment(): ?string
    {
        return $this->shipmentBlueprint->getTypeOfShipment()?->value;
    }

    public function getExplanationIfTypeOfShipmentIsOther(): ?string
    {
        return $this->shipmentBlueprint->getExplanationIfTypeOfShipmentIsOther();
    }

    public function getComment(): string
    {
        return $this->shipmentBlueprint->getComment() ?? '';
    }

    public function getOfficeOfOrigin(): string
    {
        return $this->shipmentBlueprint->getOfficeOfOrigin() ?? '';
    }

    /**
     * @return string[]
     */
    public function getPermitNumbers(): array
    {
        return $this->shipmentBlueprint->getPermitNumbers();
    }

    /**
     * @return string[]
     */
    public function getCertificateNumbers(): array
    {
        return $this->shipmentBlueprint->getCertificateNumbers();
    }

    /**
     * @return MoneyValue[]
     */
    public function getFees(): array
    {
        $fees = [];
        foreach ($this->shipmentBlueprint->getFees() as $fee) {
            $fees[$fee->getType()->value] = $fee->getAmount();
        }

        return $fees;
    }

    public function getFee(string $feeType): ?MoneyValue
    {
        return $this->shipmentBlueprint->getTotalFeesOfType(FeeType::from($feeType));
    }

    public function getTotalFees(): MoneyValue
    {
        return $this->shipmentBlueprint->getTotalFees();
    }

    public function getTotalValue(): ?MoneyValue
    {
        return $this->shipmentBlueprint->getTotalValue();
    }

    public function getInvoiceDate(): ?string
    {
        return $this->shipmentBlueprint->getInvoiceDate();
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->shipmentBlueprint->getInvoiceNumber();
    }

    public function getShipmentBlueprint(): ShipmentBlueprint
    {
        return $this->shipmentBlueprint;
    }
}
