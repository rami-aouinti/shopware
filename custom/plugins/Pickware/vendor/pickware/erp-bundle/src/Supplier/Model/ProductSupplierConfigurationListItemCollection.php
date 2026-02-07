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
 * @method void add(ProductSupplierConfigurationListItemEntity $entity)
 * @method void set(string $key, ProductSupplierConfigurationListItemEntity $entity)
 * @method ProductSupplierConfigurationListItemEntity[] getIterator()
 * @method ProductSupplierConfigurationListItemEntity[] getElements()
 * @method ProductSupplierConfigurationListItemEntity|null get(string $key)
 * @method ProductSupplierConfigurationListItemEntity|null first()
 * @method ProductSupplierConfigurationListItemEntity|null last()
 *
 * @extends EntityCollection<ProductSupplierConfigurationListItemEntity>
 */
class ProductSupplierConfigurationListItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductSupplierConfigurationListItemEntity::class;
    }
}
