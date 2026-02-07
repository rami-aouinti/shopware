<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ProductBinLocation
{
    private string $productId;
    private string $binLocationId;

    public function __construct(string $productId, string $binLocationId)
    {
        $this->productId = $productId;
        $this->binLocationId = $binLocationId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getBinLocationId(): string
    {
        return $this->binLocationId;
    }
}
