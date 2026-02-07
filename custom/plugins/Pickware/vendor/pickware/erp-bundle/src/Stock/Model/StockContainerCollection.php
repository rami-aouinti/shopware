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

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StockContainerEntity $entity)
 * @method void set(string $key, StockContainerEntity $entity)
 * @method StockContainerEntity[] getIterator()
 * @method StockContainerEntity[] getElements()
 * @method StockContainerEntity|null get(string $key)
 * @method StockContainerEntity|null first()
 * @method StockContainerEntity|null last()
 */
class StockContainerCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StockContainerEntity::class;
    }
}
