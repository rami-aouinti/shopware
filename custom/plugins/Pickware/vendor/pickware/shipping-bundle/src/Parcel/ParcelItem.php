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
use Pickware\ShippingBundle\Shipment\Country;
use Pickware\UnitsOfMeasurement\Dimensions\BoxDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;

class ParcelItem implements JsonSerializable
{
    private int $quantity;
    private ?string $name;
    private ?Weight $unitWeight;
    private ?BoxDimensions $unitDimensions;
    private ?string $productNumber;
    private ?ParcelItemCustomsInformation $customsInformation;

    /**
     * A detailed description of each article in the item, e.g. "men's cotton shirts". General descriptions,
     * e.g. "spare parts", "samples" or "food products" are not permitted.
     *
     * Field number CN 23: 1
     * Field number on CN 22: 1
     */
    private ?string $customsDescription;

    /**
     * Customs value (Zollwert) of ONE item.
     *
     * This is neither the price of the product on the invoice, nor the net price. It is a completely independent value
     * that has to be entered by the user.
     *
     * Field number CN 23: 5 (Attention: the field requires you to put the TOTAL customs value of ALL items)
     * Field number on CN 22: 3 (Attention: the field requires you to put the TOTAL customs value of ALL items)
     */
    private ?MoneyValue $unitPrice;

    /**
     * The HS tariff number (Zolltarifnummer)
     *
     * Field number CN 23: 7
     * Field number on CN 22: 4
     */
    private ?string $tariffNumber;

    /**
     * ISO 3166-1 alpha-2 (2 character) code (e.g. DE for Germany)
     *
     * Country of origin of the item / Ursprungsland der Ware
     *
     * Field number CN 23: 8
     * Field number on CN 22: 5
     */
    private ?Country $countryOfOrigin;

    public function __construct(
        int $quantity,
        ?Weight $unitWeight = null,
        ?MoneyValue $unitPrice = null,
        ?BoxDimensions $unitDimensions = null,
        ?string $name = null,
        ?string $customsDescription = null,
        ?string $tariffNumber = null,
        ?Country $countryOfOrigin = null,
        ?string $productNumber = null,
    ) {
        $this->quantity = $quantity;
        $this->unitWeight = $unitWeight;
        $this->unitPrice = $unitPrice;
        $this->unitDimensions = $unitDimensions;
        $this->name = $name;
        $this->customsDescription = $customsDescription;
        $this->tariffNumber = $tariffNumber;
        $this->countryOfOrigin = $countryOfOrigin;
        $this->customsInformation = new ParcelItemCustomsInformation($this);
        $this->productNumber = $productNumber;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'productNumber' => $this->productNumber,
            'unitPrice' => $this->unitPrice,
            'unitWeight' => $this->unitWeight,
            'unitDimensions' => $this->unitDimensions,
            'quantity' => $this->quantity,
            'countryOfOrigin' => $this->countryOfOrigin,
            'tariffNumber' => $this->tariffNumber,
            'customsDescription' => $this->customsDescription,
            'customsInformation' => $this->customsInformation,
        ];
    }

    public static function fromArray(array $array): self
    {
        if (isset($array['customsInformation'])) {
            $customsInformationArray = $array['customsInformation'];

            if (!isset($array['tariffNumber'])) {
                $array['tariffNumber'] = $customsInformationArray['tariffNumber'] ?? null;
            }

            if (!isset($array['customsDescription'])) {
                $array['customsDescription'] = $customsInformationArray['description'] ?? null;
            }
            if (!isset($array['countryOfOrigin'])) {
                $array['countryOfOrigin'] = isset($customsInformationArray['countryIsoOfOrigin']) ? ['iso2Code' => $customsInformationArray['countryIsoOfOrigin']] : null;
            }
            if (!isset($array['unitPrice'])) {
                $array['unitPrice'] = $customsInformationArray['customsValue'] ?? null;
            }

            trigger_error(
                'The customs information is now expected as part of the ParcelItem directly. ' .
                'Please update your code to set the customs information on the ParcelItem.',
                E_USER_DEPRECATED,
            );
        }

        return new self(
            quantity: (int)($array['quantity'] ?? 0),
            unitWeight: isset($array['unitWeight']) ? Weight::fromArray($array['unitWeight']) : null,
            unitPrice: isset($array['unitPrice']) ? MoneyValue::fromArray($array['unitPrice']) : null,
            unitDimensions: isset($array['unitDimensions']) ? BoxDimensions::fromArray($array['unitDimensions']) : null,
            name: $array['name'] ?? null,
            customsDescription: $array['customsDescription'] ?? null,
            tariffNumber: $array['tariffNumber'] ?? null,
            countryOfOrigin: isset($array['countryOfOrigin']) ? Country::fromArray($array['countryOfOrigin']) : null,
            productNumber: $array['productNumber'] ?? null,
        );
    }

    public function __clone()
    {
        if ($this->unitWeight) {
            $this->unitWeight = clone $this->unitWeight;
        }
        if ($this->unitDimensions) {
            $this->unitDimensions = clone $this->unitDimensions;
        }
        if ($this->unitPrice) {
            $this->unitPrice = clone $this->unitPrice;
        }
    }

    public function getTotalWeight(): ?Weight
    {
        if (!$this->getUnitWeight()) {
            return null;
        }

        return $this->unitWeight->multiplyWithScalar($this->quantity);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getUnitWeight(): ?Weight
    {
        return $this->unitWeight;
    }

    public function setUnitWeight(?Weight $unitWeight): void
    {
        $this->unitWeight = $unitWeight;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitDimensions(): ?BoxDimensions
    {
        return $this->unitDimensions;
    }

    public function setUnitDimensions(?BoxDimensions $unitDimensions): void
    {
        $this->unitDimensions = $unitDimensions;
    }

    /**
     * @deprecated Will be removed in 3.0.0. Use fields on ShipmentBlueprint directly instead.
     */
    public function getCustomsInformation(): ?ParcelItemCustomsInformation
    {
        return $this->customsInformation;
    }

    /**
     * @deprecated Will be removed in 3.0.0. Use fields on ShipmentBlueprint directly instead.
     */
    public function setCustomsInformation(?ParcelItemCustomsInformation $customsInformation): void
    {
        $this->customsInformation = $customsInformation;
    }

    public function getUnitPrice(): ?MoneyValue
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?MoneyValue $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getCustomsDescription(): ?string
    {
        return $this->customsDescription;
    }

    public function setCustomsDescription(?string $customsDescription): void
    {
        $this->customsDescription = $customsDescription;
    }

    public function getTariffNumber(): ?string
    {
        return $this->tariffNumber;
    }

    public function setTariffNumber(?string $tariffNumber): void
    {
        $this->tariffNumber = $tariffNumber;
    }

    public function getCountryOfOrigin(): ?Country
    {
        return $this->countryOfOrigin;
    }

    public function setCountryOfOrigin(?Country $countryOfOrigin): void
    {
        $this->countryOfOrigin = $countryOfOrigin;
    }

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    public function setProductNumber(?string $productNumber): void
    {
        $this->productNumber = $productNumber;
    }

    /**
     * Returns true if the given ParcelItem has the same attributes as this ParcelItem except for the quantity.
     */
    public function hasSameAttributesAs(ParcelItem $parcelItem): bool
    {
        return $this->getName() === $parcelItem->getName()
            && $this->getTariffNumber() === $parcelItem->getTariffNumber()
            && $this->getCustomsDescription() === $parcelItem->getCustomsDescription()
            && $this->getCountryOfOrigin()?->getIso2Code() === $parcelItem->getCountryOfOrigin()?->getIso2Code()
            && $this->comparePrice($parcelItem)
            && $this->compareDimensions($parcelItem)
            && $this->compareWeights($parcelItem);
    }

    private function comparePrice(ParcelItem $parcelItem): bool
    {
        if (!$this->getUnitPrice() && !$parcelItem->getUnitPrice()) {
            return true;
        }

        if ($this->getUnitPrice() && $parcelItem->getUnitPrice()) {
            return $this->getUnitPrice()?->getValue() === $parcelItem->getUnitPrice()?->getValue()
                && $this->getUnitPrice()->getCurrency()->equals($parcelItem->getUnitPrice()->getCurrency());
        }

        return false;
    }

    private function compareDimensions(ParcelItem $parcelItem): bool
    {
        if (!$this->getUnitDimensions() && !$parcelItem->getUnitDimensions()) {
            return true;
        }

        if ($this->getUnitDimensions() && $parcelItem->getUnitDimensions()) {
            return $this->getUnitDimensions()->getLength()->isEqualTo($parcelItem->getUnitDimensions()->getLength(), new Length(1, 'mm'))
                && $this->getUnitDimensions()->getWidth()->isEqualTo($parcelItem->getUnitDimensions()->getWidth(), new Length(1, 'mm'))
                && $this->getUnitDimensions()->getHeight()->isEqualTo($parcelItem->getUnitDimensions()->getHeight(), new Length(1, 'mm'));
        }

        return false;
    }

    private function compareWeights(ParcelItem $parcelItem): bool
    {
        if (!$this->getUnitWeight() && !$parcelItem->getUnitWeight()) {
            return true;
        }

        if ($this->getUnitWeight() && $parcelItem->getUnitWeight()) {
            return $this->getUnitWeight()->isEqualTo($parcelItem->getUnitWeight(), new Weight(1, 'g'));
        }

        return false;
    }

    public function getTotalPrice(): ?MoneyValue
    {
        if (!$this->getUnitPrice()) {
            return null;
        }

        // The total price is the unit price multiplied by the quantity.
        return $this->getUnitPrice()->multiply($this->getQuantity());
    }
}
