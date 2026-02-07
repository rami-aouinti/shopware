<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(ProductSetConfigurationEntity $entity)
 * @method void set(string $key, ProductSetConfigurationEntity $entity)
 * @method ProductSetConfigurationEntity[] getIterator()
 * @method ProductSetConfigurationEntity[] getElements()
 * @method ProductSetConfigurationEntity|null get(string $key)
 * @method ProductSetConfigurationEntity|null first()
 * @method ProductSetConfigurationEntity|null last()
 */
class ProductSetConfigurationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductSetConfigurationEntity::class;
    }
}
