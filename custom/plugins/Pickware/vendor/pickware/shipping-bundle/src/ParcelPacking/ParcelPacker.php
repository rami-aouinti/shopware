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

use Pickware\ShippingBundle\Parcel\Parcel;

interface ParcelPacker
{
    /**
     * Repack a parcel into multiple parcels based on a parcel packing configuration
     *
     * @return Parcel[]
     */
    public function repackParcel(Parcel $parcel, ParcelPackingConfiguration $parcelPackingConfiguration): array;
}
