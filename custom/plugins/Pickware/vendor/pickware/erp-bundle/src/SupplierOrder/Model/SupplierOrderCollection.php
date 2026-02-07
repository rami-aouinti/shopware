<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(SupplierOrderEntity $entity)
 * @method void set(string $key, SupplierOrderEntity $entity)
 * @method SupplierOrderEntity[] getIterator()
 * @method SupplierOrderEntity[] getElements()
 * @method SupplierOrderEntity|null get(string $key)
 * @method SupplierOrderEntity|null first()
 * @method SupplierOrderEntity|null last()
 */
class SupplierOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SupplierOrderEntity::class;
    }
}
