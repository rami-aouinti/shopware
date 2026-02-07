<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(SupplierEntity $entity)
 * @method void set(string $key, SupplierEntity $entity)
 * @method SupplierEntity[] getIterator()
 * @method SupplierEntity[] getElements()
 * @method SupplierEntity|null get(string $key)
 * @method SupplierEntity|null first()
 * @method SupplierEntity|null last()
 */
class SupplierCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SupplierEntity::class;
    }
}
