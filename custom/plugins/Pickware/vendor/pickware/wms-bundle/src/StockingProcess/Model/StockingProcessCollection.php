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

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StockingProcessEntity $entity)
 * @method void set(string $key, StockingProcessEntity $entity)
 * @method StockingProcessEntity[] getIterator()
 * @method StockingProcessEntity[] getElements()
 * @method StockingProcessEntity|null get(string $key)
 * @method StockingProcessEntity|null first()
 * @method StockingProcessEntity|null last()
 *
 * @extends EntityCollection<StockingProcessEntity>
 */
class StockingProcessCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StockingProcessEntity::class;
    }
}
