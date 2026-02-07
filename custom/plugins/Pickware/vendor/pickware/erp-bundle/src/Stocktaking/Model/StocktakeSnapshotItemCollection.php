<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StocktakeSnapshotItemEntity $entity)
 * @method void set(string $key, StocktakeSnapshotItemEntity $entity)
 * @method StocktakeSnapshotItemEntity[] getIterator()
 * @method StocktakeSnapshotItemEntity[] getElements()
 * @method StocktakeSnapshotItemEntity|null get(string $key)
 * @method StocktakeSnapshotItemEntity|null first()
 * @method StocktakeSnapshotItemEntity|null last()
 */
class StocktakeSnapshotItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StocktakeSnapshotItemEntity::class;
    }
}
