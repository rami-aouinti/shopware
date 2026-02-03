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

use InvalidArgumentException;
use JsonSerializable;
use Pickware\MoneyBundle\Currency;
use Pickware\MoneyBundle\CurrencyConverter;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\Shipment\Fee;
use Pickware\ShippingBundle\Shipment\FeeType;
use Pickware\UnitsOfMeasurement\Dimensions\BoxDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;
use Shopware\Core\Framework\Context;

class Parcel implements JsonSerializable
{
    /**
     * @param ParcelItem[] $items
     */
    public function __construct(
        private array $items = [],
        private Weight $fillerWeight = new Weight(0, 'kg'),
        private ?BoxDimensions $dimensions = null,
        private ?Weight $weightOverwrite = null,
        private ?string $customerReference = null,
        private ?ParcelCustomsInformation $customsInformation = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'dimensions' => $this->dimensions,
            'fillerWeight' => $this->fillerWeight,
            'weightOverwrite' => $this->weightOverwrite,
            'customerReference' => $this->customerReference,
            'customsInformation' => $this->customsInformation,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            items: array_map(fn(array $itemArray) => ParcelItem::fromArray($itemArray), $array['items'] ?? []),
            fillerWeight: isset($array['fillerWeight']) ? Weight::fromArray($array['fillerWeight']) : new Weight(0, 'kg'),
            dimensions: isset($array['dimensions']) ? BoxDimensions::fromArray($array['dimensions']) : null,
            weightOverwrite: isset($array['weightOverwrite']) ? Weight::fromArray($array['weightOverwrite']) : null,
            customerReference: $array['customerReference'] ?? null,
        );
    }

    /**
     * Creates a copy of the parcel but without any item
     */
    public function createCopyWithoutItems(): self
    {
        $self = new self();
        $self->fillerWeight = $this->fillerWeight;
        $self->dimensions = $this->dimensions;
        $self->weightOverwrite = $this->weightOverwrite;
        $self->customerReference = $this->customerReference;
        $self->customsInformation = $this->customsInformation;

        return $self;
    }

    public function getTotalWeight(): ?Weight
    {
        if ($this->weightOverwrite) {
            return $this->weightOverwrite;
        }

        $totalItemWeight = $this->getTotalItemWeight();
        if ($totalItemWeight === null) {
            return null;
        }

        return Weight::sum($this->fillerWeight, $totalItemWeight);
    }

    private function getTotalItemWeight(): ?Weight
    {
        $weights = array_map(fn(ParcelItem $parcelItem) => $parcelItem->getTotalWeight(), $this->items);

        if (in_array(null, $weights, true)) {
            return null;
        }

        return Weight::sum(...$weights);
    }

    /**
     * @return string Returns a human-readable description of this package
     */
    public function getDescription(): string
    {
        return sprintf(
            'Parcel with items %s',
            implode(', ', array_map(fn(ParcelItem $item) => $item->getName(), $this->items)),
        );
    }

    /**
     * @return ParcelItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param ParcelItem[] $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function addItem(ParcelItem $item): void
    {
        $this->items[] = $item;
    }

    public function getDimensions(): ?BoxDimensions
    {
        return $this->dimensions;
    }

    public function setDimensions(?BoxDimensions $dimensions): void
    {
        $this->dimensions = $dimensions;
    }

    public function getFillerWeight(): Weight
    {
        return $this->fillerWeight;
    }

    public function setFillerWeight(Weight $fillerWeight): void
    {
        $this->fillerWeight = $fillerWeight;
    }

    public function getCustomerReference(): ?string
    {
        return $this->customerReference;
    }

    public function setCustomerReference(?string $customerReference): void
    {
        $this->customerReference = $customerReference;
    }

    public function getWeightOverwrite(): ?Weight
    {
        return $this->weightOverwrite;
    }

    public function setWeightOverwrite(?Weight $weightOverwrite): void
    {
        $this->weightOverwrite = $weightOverwrite;
    }

    /**
     * @deprecated Will be removed in 3.0.0. Use fields on ShipmentBlueprint instead.
     */
    public function getCustomsInformation(): ?ParcelCustomsInformation
    {
        return $this->customsInformation;
    }

    /**
     * @deprecated Will be removed in 3.0.0. Use fields on ShipmentBlueprint instead.
     */
    public function setCustomsInformation(?ParcelCustomsInformation $customsInformation): void
    {
        if ($customsInformation && $customsInformation->getParcel() !== $this) {
            throw new InvalidArgumentException(sprintf(
                'The referenced %s in passed %s is not $this.',
                self::class,
                ParcelCustomsInformation::class,
            ));
        }
        $this->customsInformation = $customsInformation;
    }

    /**
     * Returns the total customs value of all items without the fees ontop.
     * If any of the customs value of the items is not set, this value cannot be determined.
     */
    public function getTotalValue(): ?MoneyValue
    {
        $customsValues = array_map(fn(ParcelItem $item) => $item->getUnitPrice()?->multiply($item->getQuantity()), $this->items);

        if (in_array(null, $customsValues)) {
            return null;
        }

        return MoneyValue::sum(
            ...$customsValues,
        );
    }

    /**
     * @deprecated Will be removed in 3.0.0. Use ShipmentBlueprint::convertAllMoneyValuesToSameCurrency instead.
     */
    public function convertAllMoneyValuesToSameCurrency(
        CurrencyConverter $currencyConverter,
        Currency $targetCurrency,
        Context $context,
    ): void {
        if ($this->customsInformation) {
            $fees = array_map(
                fn(MoneyValue $moneyValue, string $feeType) => new Fee(
                    type: FeeType::from($feeType),
                    amount: $currencyConverter->convertMoneyValueToCurrency($moneyValue, $targetCurrency, $context),
                ),
                $this->customsInformation->getFees(),
                array_keys($this->customsInformation->getFees()),
            );
            $this->customsInformation->getShipmentBlueprint()->setFees($fees);
        }

        foreach ($this->getItems() as $item) {
            $customsInformation = $item->getCustomsInformation();
            if (!$customsInformation || !$customsInformation->getCustomsValue()) {
                continue;
            }
            $item->setUnitPrice($currencyConverter->convertMoneyValueToCurrency(
                $customsInformation->getCustomsValue(),
                $targetCurrency,
                $context,
            ));
        }
    }

    public function recalculateFillerWeight(Weight $absoluteSurcharge, float $relativeSurcharge): void
    {
        $this->fillerWeight = $absoluteSurcharge;

        $totalItemWeight = $this->getTotalItemWeight();
        if ($totalItemWeight === null) {
            return;
        }

        $this->fillerWeight = $this->fillerWeight->add(
            $totalItemWeight->multiplyWithScalar($relativeSurcharge),
        );
    }
}
