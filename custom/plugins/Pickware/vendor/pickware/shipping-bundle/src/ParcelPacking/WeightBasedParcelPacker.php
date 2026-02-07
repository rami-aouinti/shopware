<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelPacking;

use Pickware\ShippingBundle\Notifications\NotificationService;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\ParcelPacking\BinPacking\BinPackingException;
use Pickware\ShippingBundle\ParcelPacking\BinPacking\WeightBasedBinPacker;

class WeightBasedParcelPacker implements ParcelPacker
{
    private WeightBasedBinPacker $binPacker;
    private NotificationService $notificationService;

    public function __construct(WeightBasedBinPacker $binPacker, NotificationService $notificationService)
    {
        $this->binPacker = $binPacker;
        $this->notificationService = $notificationService;
    }

    /**
     * @return Parcel[]
     */
    public function repackParcel(Parcel $parcel, ParcelPackingConfiguration $parcelPackingConfiguration): array
    {
        $parcel->recalculateFillerWeight(
            $parcelPackingConfiguration->getFillerWeightAbsoluteSurchargePerParcel(),
            $parcelPackingConfiguration->getFillerWeightRelativeSurchargePerParcel(),
        );

        if ($parcel->getTotalWeight() === null || $parcel->getTotalWeight()->isZero()) {
            if ($parcelPackingConfiguration->getFallbackParcelWeight()) {
                $parcel->setWeightOverwrite($parcelPackingConfiguration->getFallbackParcelWeight());
            }

            return [$parcel];
        }

        $maxParcelWeight = $parcelPackingConfiguration->getMaxParcelWeight();
        if ($maxParcelWeight === null) {
            return [$parcel];
        }

        if (!$maxParcelWeight->isGreaterThan($parcelPackingConfiguration->getFillerWeightAbsoluteSurchargePerParcel())) {
            $this->notificationService->emit(ParcelPackingNotification::absoluteFillerWeightSurchargeIsHigherThanMaxParcelWeight(
                absoluteFillerWeightSurchargePerParcel: $parcelPackingConfiguration->getFillerWeightAbsoluteSurchargePerParcel(),
                maxParcelWeight: $maxParcelWeight,
            ));

            return [$parcel];
        }

        $binCapacity = $maxParcelWeight
            ->subtract($parcelPackingConfiguration->getFillerWeightAbsoluteSurchargePerParcel())
            ->multiplyWithScalar(1 / (1 + $parcelPackingConfiguration->getFillerWeightRelativeSurchargePerParcel()));
        try {
            $bins = $this->binPacker->packIntoBins(
                $parcel->getItems(),
                $binCapacity,
            );
        } catch (BinPackingException $e) {
            $this->notificationService->emit(ParcelPackingNotification::binPackingFailed($e));

            return [$parcel];
        }

        $parcels = [];
        foreach ($bins as $bin) {
            $subParcel = $parcel->createCopyWithoutItems();
            $subParcel->setItems($bin);
            $subParcel->recalculateFillerWeight(
                $parcelPackingConfiguration->getFillerWeightAbsoluteSurchargePerParcel(),
                $parcelPackingConfiguration->getFillerWeightRelativeSurchargePerParcel(),
            );
            $parcels[] = $subParcel;
        }

        return $parcels;
    }
}
