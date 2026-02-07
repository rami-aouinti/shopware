<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(DeliveryLifecycleEventUserRoleEntity $entity)
 * @method void set(string $key, DeliveryLifecycleEventUserRoleEntity $entity)
 * @method DeliveryLifecycleEventUserRoleEntity[] getIterator()
 * @method DeliveryLifecycleEventUserRoleEntity[] getElements()
 * @method DeliveryLifecycleEventUserRoleEntity|null get(string $key)
 * @method DeliveryLifecycleEventUserRoleEntity|null first()
 * @method DeliveryLifecycleEventUserRoleEntity|null last()
 *
 * @extends EntityCollection<DeliveryLifecycleEventUserRoleEntity>
 */
class DeliveryLifecycleEventUserRoleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DeliveryLifecycleEventUserRoleEntity::class;
    }
}
