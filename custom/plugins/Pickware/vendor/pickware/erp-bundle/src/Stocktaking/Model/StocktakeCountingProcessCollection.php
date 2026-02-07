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
 * @method void add(StocktakeCountingProcessEntity $entity)
 * @method void set(string $key, StocktakeCountingProcessEntity $entity)
 * @method StocktakeCountingProcessEntity[] getIterator()
 * @method StocktakeCountingProcessEntity[] getElements()
 * @method StocktakeCountingProcessEntity|null get(string $key)
 * @method StocktakeCountingProcessEntity|null first()
 * @method StocktakeCountingProcessEntity|null last()
 */
class StocktakeCountingProcessCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StocktakeCountingProcessEntity::class;
    }
}
