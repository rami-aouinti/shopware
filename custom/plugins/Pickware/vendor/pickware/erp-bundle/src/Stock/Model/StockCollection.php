<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<StockEntity>
 */
class StockCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StockEntity::class;
    }

    public function getProductQuantityLocations(): ProductQuantityLocationImmutableCollection
    {
        return ImmutableCollection::create($this)->map(
            fn(StockEntity $stock) => $stock->getProductQuantityLocation(),
            ProductQuantityLocationImmutableCollection::class,
        );
    }

    public function getProductQuantities(): ProductQuantityImmutableCollection
    {
        return $this->getProductQuantityLocations()->groupByProductId();
    }
}
