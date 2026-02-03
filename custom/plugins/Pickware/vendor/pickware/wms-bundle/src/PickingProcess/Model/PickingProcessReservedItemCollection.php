<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\Model;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickingProcessReservedItemEntity $entity)
 * @method void set(string $key, PickingProcessReservedItemEntity $entity)
 * @method PickingProcessReservedItemEntity[] getIterator()
 * @method PickingProcessReservedItemEntity[] getElements()
 * @method PickingProcessReservedItemEntity|null get(string $key)
 * @method PickingProcessReservedItemEntity|null first()
 * @method PickingProcessReservedItemEntity|null last()
 *
 * @extends EntityCollection<PickingProcessReservedItemEntity>
 */
class PickingProcessReservedItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingProcessReservedItemEntity::class;
    }

    public function getProductQuantityLocations(): ProductQuantityLocationImmutableCollection
    {
        return (new ImmutableCollection($this->elements))->map(
            fn(PickingProcessReservedItemEntity $reservedItem) => $reservedItem->getProductQuantityLocation(),
            ProductQuantityLocationImmutableCollection::class,
        );
    }
}
