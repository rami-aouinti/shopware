<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess\Model;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockLocationReferenceTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StockingProcessSourceEntity $entity)
 * @method void set(string $key, StockingProcessSourceEntity $entity)
 * @method StockingProcessSourceEntity[] getIterator()
 * @method StockingProcessSourceEntity[] getElements()
 * @method StockingProcessSourceEntity|null get(string $key)
 * @method StockingProcessSourceEntity|null first()
 * @method StockingProcessSourceEntity|null last()
 *
 * @extends EntityCollection<StockingProcessSourceEntity>
 */
class StockingProcessSourceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StockingProcessSourceEntity::class;
    }

    /**
     * Requires all associations to be loaded, that can hold stock.
     * See: {@link StockLocationReferenceTrait::getProductQuantityLocations()}
     */
    public function getProductQuantities(): ProductQuantityImmutableCollection
    {
        return ImmutableCollection::create($this)
            ->flatMap(
                fn(StockingProcessSourceEntity $source) => $source->getProductQuantityLocations(),
                returnType: ProductQuantityLocationImmutableCollection::class,
            )
            ->groupByProductId();
    }

    /**
     * Requires all associations to be loaded, that can hold stock.
     * See: {@link StockLocationReferenceTrait::getProductQuantityLocations()}
     */
    public function getProductQuantityLocations(): ProductQuantityLocationImmutableCollection
    {
        return ImmutableCollection::create($this)->flatMap(
            fn(StockingProcessSourceEntity $source) => $source->getProductQuantityLocations(),
            returnType: ProductQuantityLocationImmutableCollection::class,
        );
    }
}
