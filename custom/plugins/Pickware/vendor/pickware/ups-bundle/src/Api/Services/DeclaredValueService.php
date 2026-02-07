<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api\Services;

use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\UpsBundle\Adapter\UpsAdapterException;

class DeclaredValueService extends AbstractPackageService
{
    /**
     * @param array{PackageServiceOptions?: array{DeclaredValue?: array{CurrencyCode?: string, MonetaryValue?: string}}} $packageArray
     */
    public function applyToPackageArray(array &$packageArray, Parcel $parcel): void
    {
        $parcelValue = $this->calculateParcelValueFromCustomsInformation($parcel);

        if ($parcelValue === null) {
            throw UpsAdapterException::parcelHasItemsWithUndefinedValue();
        }

        if (!isset($packageArray['PackageServiceOptions'])) {
            $packageArray['PackageServiceOptions'] = [];
        }

        $packageArray['PackageServiceOptions']['DeclaredValue'] = array_filter(
            [
                'CurrencyCode' => $parcelValue->getCurrency()->getIsoCode(),
                'MonetaryValue' => (string) $parcelValue->getValue(),
            ],
        );
    }

    private function calculateParcelValueFromCustomsInformation(Parcel $parcel): ?MoneyValue
    {
        $customsValues = array_map(fn(ParcelItem $item) => $item->getUnitPrice()?->multiply($item->getQuantity()), $parcel->getItems());

        if (in_array(null, $customsValues)) {
            return null;
        }

        return MoneyValue::sum(
            ...$customsValues,
        );
    }
}
