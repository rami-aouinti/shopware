<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Warehouse;

use DateTimeZone;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;

class WarehouseExtension
{
    public static function getTimezone(WarehouseEntity $warehouseEntity): DateTimeZone
    {
        /** @phpstan-ignore function.alreadyNarrowedType (Method does not exist in older class versions) */
        if (method_exists($warehouseEntity, 'getTimezone')) {
            return $warehouseEntity->getTimezone();
        }

        return new DateTimeZone('Europe/Berlin');
    }
}
