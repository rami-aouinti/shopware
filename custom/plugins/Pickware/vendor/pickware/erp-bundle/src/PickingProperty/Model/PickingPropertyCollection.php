<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickingPropertyEntity $entity)
 * @method void set(string $key, PickingPropertyEntity $entity)
 * @method PickingPropertyEntity[] getIterator()
 * @method PickingPropertyEntity[] getElements()
 * @method PickingPropertyEntity|null get(string $key)
 * @method PickingPropertyEntity|null first()
 * @method PickingPropertyEntity|null last()
 */
class PickingPropertyCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingPropertyEntity::class;
    }
}
