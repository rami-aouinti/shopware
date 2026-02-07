<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api\Services;

use Pickware\DhlExpressBundle\Api\DhlExpressShipment;

/**
 * @phpstan-import-type DhlExpressShipmentArray from DhlExpressShipment
 */
abstract class AbstractShipmentOption
{
    /**
     * @param DhlExpressShipmentArray $shipmentArray
     */
    abstract public function applyToShipmentArray(array &$shipmentArray): void;
}
