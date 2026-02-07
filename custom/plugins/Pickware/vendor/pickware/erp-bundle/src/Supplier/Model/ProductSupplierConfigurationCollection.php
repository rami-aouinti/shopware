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
 * @method void add(ProductSupplierConfigurationEntity $entity)
 * @method void set(string $key, ProductSupplierConfigurationEntity $entity)
 * @method ProductSupplierConfigurationEntity[] getIterator()
 * @method ProductSupplierConfigurationEntity[] getElements()
 * @method ProductSupplierConfigurationEntity|null get(string $key)
 * @method ProductSupplierConfigurationEntity|null first()
 * @method ProductSupplierConfigurationEntity|null last()
 *
 * @extends EntityCollection<ProductSupplierConfigurationEntity>
 */
class ProductSupplierConfigurationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductSupplierConfigurationEntity::class;
    }

    public function getByProductIdAndSupplierId(string $productId, string $supplierId): ?ProductSupplierConfigurationEntity
    {
        return $this
            ->filter(fn(ProductSupplierConfigurationEntity $productSupplierConfiguration) => $productSupplierConfiguration->getProductId() === $productId && $productSupplierConfiguration->getSupplierId() === $supplierId)
            ->first();
    }
}
