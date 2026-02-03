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
 * @method void add(LocationTypeEntity $entity)
 * @method void set(string $key, LocationTypeEntity $entity)
 * @method LocationTypeEntity[] getIterator()
 * @method LocationTypeEntity[] getElements()
 * @method LocationTypeEntity|null get(string $key)
 * @method LocationTypeEntity|null first()
 * @method LocationTypeEntity|null last()
 */
class LocationTypeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LocationTypeEntity::class;
    }
}
