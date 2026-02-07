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
 * @method void add(ProductSetEntity $entity)
 * @method void set(string $key, ProductSetEntity $entity)
 * @method ProductSetEntity[] getIterator()
 * @method ProductSetEntity[] getElements()
 * @method ProductSetEntity|null get(string $key)
 * @method ProductSetEntity|null first()
 * @method ProductSetEntity|null last()
 */
class ProductSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductSetEntity::class;
    }
}
