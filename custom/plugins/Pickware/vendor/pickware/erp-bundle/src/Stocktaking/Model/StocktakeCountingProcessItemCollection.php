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
 * @method void add(StocktakeCountingProcessItemEntity $entity)
 * @method void set(string $key, StocktakeCountingProcessItemEntity $entity)
 * @method StocktakeCountingProcessItemEntity[] getIterator()
 * @method StocktakeCountingProcessItemEntity[] getElements()
 * @method StocktakeCountingProcessItemEntity|null get(string $key)
 * @method StocktakeCountingProcessItemEntity|null first()
 * @method StocktakeCountingProcessItemEntity|null last()
 */
class StocktakeCountingProcessItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StocktakeCountingProcessItemEntity::class;
    }
}
