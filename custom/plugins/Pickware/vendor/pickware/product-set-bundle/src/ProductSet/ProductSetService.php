<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\ProductSet;

class ProductSetService
{
    public function __construct() {}

    /**
     * This method exists solely for feature detection in the pickware-wms plugin
     */
    public function areProductSetsAvailable(): bool
    {
        return true;
    }
}
