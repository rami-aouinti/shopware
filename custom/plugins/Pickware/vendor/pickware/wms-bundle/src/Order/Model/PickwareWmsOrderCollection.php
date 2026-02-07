<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Order\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickwareWmsOrderEntity $entity)
 * @method void set(string $key, PickwareWmsOrderEntity $entity)
 * @method PickwareWmsOrderEntity[] getIterator()
 * @method PickwareWmsOrderEntity[] getElements()
 * @method PickwareWmsOrderEntity|null get(string $key)
 * @method PickwareWmsOrderEntity|null first()
 * @method PickwareWmsOrderEntity|null last()
 *
 * @extends EntityCollection<PickwareWmsOrderEntity>
 */
class PickwareWmsOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickwareWmsOrderEntity::class;
    }
}
