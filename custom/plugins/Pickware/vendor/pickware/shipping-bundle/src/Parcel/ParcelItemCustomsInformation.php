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

/**
 * @deprecated Will be removed in 3.0.0. Use fields on ParcelItem directly instead.
 */
class ParcelItemCustomsInformation implements JsonSerializable
{
    private ParcelItem $parcelItem;

    public function __construct(ParcelItem $parcelItem)
    {
        $this->parcelItem = $parcelItem;
        $parcelItem->setCustomsInformation($this);
    }

    public function jsonSerialize(): array
    {
        return [
            'description' => $this->parcelItem->getCustomsDescription(),
            'customsValue' => $this->parcelItem->getUnitPrice(),
            'tariffNumber' => $this->parcelItem->getTariffNumber(),
            'countryIsoOfOrigin' => $this->parcelItem->getCountryOfOrigin() ? mb_strtoupper($this->parcelItem->getCountryOfOrigin()->getIso2Code()) : null,
        ];
    }

    public function getDescription(): string
    {
        return $this->parcelItem->getCustomsDescription() ?? '';
    }

    public function getCustomsValue(): ?MoneyValue
    {
        return $this->parcelItem->getUnitPrice();
    }

    public function getTariffNumber(): ?string
    {
        return $this->parcelItem->getTariffNumber();
    }

    public function getCountryIsoOfOrigin(): ?string
    {
        return $this->parcelItem->getCountryOfOrigin() ? mb_strtoupper($this->parcelItem->getCountryOfOrigin()->getIso2Code()) : null;
    }
}
