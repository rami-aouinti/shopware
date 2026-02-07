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
 * @method void add(ProductStockLocationMappingEntity $entity)
 * @method void set(string $key, ProductStockLocationMappingEntity $entity)
 * @method ProductStockLocationMappingEntity[] getIterator()
 * @method ProductStockLocationMappingEntity[] getElements()
 * @method ProductStockLocationMappingEntity|null get(string $key)
 * @method ProductStockLocationMappingEntity|null first()
 * @method ProductStockLocationMappingEntity|null last()
 *
 * @extends EntityCollection<ProductStockLocationMappingEntity>
 */
class ProductStockLocationMappingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductStockLocationMappingEntity::class;
    }
}
