<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockMovementProcess\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StockMovementProcessEntity $entity)
 * @method void set(string $key, StockMovementProcessEntity $entity)
 * @method StockMovementProcessEntity[] getIterator()
 * @method StockMovementProcessEntity[] getElements()
 * @method StockMovementProcessEntity|null get(string $key)
 * @method StockMovementProcessEntity|null first()
 * @method StockMovementProcessEntity|null last()
 */
class StockMovementProcessCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StockMovementProcessEntity::class;
    }
}
