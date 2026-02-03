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
 * @method void add(SupplierOrderLineItemEntity $entity)
 * @method void set(string $key, SupplierOrderLineItemEntity $entity)
 * @method SupplierOrderLineItemEntity[] getIterator()
 * @method SupplierOrderLineItemEntity[] getElements()
 * @method SupplierOrderLineItemEntity|null get(string $key)
 * @method SupplierOrderLineItemEntity|null first()
 * @method SupplierOrderLineItemEntity|null last()
 *
 * @extends EntityCollection<SupplierOrderLineItemEntity>
 */
class SupplierOrderLineItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SupplierOrderLineItemEntity::class;
    }
}
