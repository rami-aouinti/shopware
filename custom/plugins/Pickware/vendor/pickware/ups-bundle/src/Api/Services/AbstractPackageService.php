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

use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\UpsBundle\Api\UpsShipment;

/**
 * @phpstan-import-type SerializedPackage from UpsShipment
 */
abstract class AbstractPackageService
{
    /**
     * @param SerializedPackage $packageArray
     */
    abstract public function applyToPackageArray(array &$packageArray, Parcel $parcel): void;
}
