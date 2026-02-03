<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelPacking\BinPacking;

use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;

/**
 * Implementations should be a solution to the bin packing problem with weights.
 * @see https://en.wikipedia.org/wiki/Bin_packing_problem
 */
interface WeightBasedBinPacker
{
    /**
     * Packs n items ($itemsToDistribute) into k bins with a given capacity and tries to keep k as low as possible.
     *
     * @param ParcelItem[] $itemsToDistribute
     * @throws BinPackingException
     * @return ParcelItem[][]
     */
    public function packIntoBins(array $itemsToDistribute, Weight $binCapacity): array;
}
