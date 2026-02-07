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
 * @method void add(PickingProcessLifecycleEventUserRoleEntity $entity)
 * @method void set(string $key, PickingProcessLifecycleEventUserRoleEntity $entity)
 * @method PickingProcessLifecycleEventUserRoleEntity[] getIterator()
 * @method PickingProcessLifecycleEventUserRoleEntity[] getElements()
 * @method PickingProcessLifecycleEventUserRoleEntity|null get(string $key)
 * @method PickingProcessLifecycleEventUserRoleEntity|null first()
 * @method PickingProcessLifecycleEventUserRoleEntity|null last()
 *
 * @extends EntityCollection<PickingProcessLifecycleEventUserRoleEntity>
 */
class PickingProcessLifecycleEventUserRoleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingProcessLifecycleEventUserRoleEntity::class;
    }
}
