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
 * @method void add(ProductStockLocationConfigurationEntity $entity)
 * @method void set(string $key, ProductStockLocationConfigurationEntity $entity)
 * @method ProductStockLocationConfigurationEntity[] getIterator()
 * @method ProductStockLocationConfigurationEntity[] getElements()
 * @method ProductStockLocationConfigurationEntity|null get(string $key)
 * @method ProductStockLocationConfigurationEntity|null first()
 * @method ProductStockLocationConfigurationEntity|null last()
 *
 * @extends EntityCollection<ProductStockLocationConfigurationEntity>
 */
class ProductStockLocationConfigurationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductStockLocationConfigurationEntity::class;
    }
}
