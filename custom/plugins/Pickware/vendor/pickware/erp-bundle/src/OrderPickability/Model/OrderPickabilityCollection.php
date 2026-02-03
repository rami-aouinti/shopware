<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(OrderPickabilityEntity $entity)
 * @method void set(string $key, OrderPickabilityEntity $entity)
 * @method OrderPickabilityEntity[] getIterator()
 * @method OrderPickabilityEntity[] getElements()
 * @method OrderPickabilityEntity|null get(string $key)
 * @method OrderPickabilityEntity|null first()
 * @method OrderPickabilityEntity|null last()
 */
class OrderPickabilityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderPickabilityEntity::class;
    }
}
