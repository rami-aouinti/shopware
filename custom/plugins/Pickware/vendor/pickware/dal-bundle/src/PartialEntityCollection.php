<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;

/**
 * @method void add(PartialEntity $entity)
 * @method void set(string $key, PartialEntity $entity)
 * @method PartialEntity[] getIterator()
 * @method PartialEntity[] getElements()
 * @method PartialEntity|null get(string $key)
 * @method PartialEntity|null first()
 * @method PartialEntity|null last()
 */
class PartialEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PartialEntity::class;
    }
}
