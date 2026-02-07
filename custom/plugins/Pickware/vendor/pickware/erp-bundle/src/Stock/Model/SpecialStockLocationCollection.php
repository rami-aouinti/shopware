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
 * @method void add(SpecialStockLocationEntity $entity)
 * @method void set(string $key, SpecialStockLocationEntity $entity)
 * @method SpecialStockLocationEntity[] getIterator()
 * @method SpecialStockLocationEntity[] getElements()
 * @method SpecialStockLocationEntity|null get(string $key)
 * @method SpecialStockLocationEntity|null first()
 * @method SpecialStockLocationEntity|null last()
 */
class SpecialStockLocationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SpecialStockLocationEntity::class;
    }
}
