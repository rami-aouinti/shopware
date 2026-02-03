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
 * @method void add(PickingProcessLifecycleEventEntity $entity)
 * @method void set(string $key, PickingProcessLifecycleEventEntity $entity)
 * @method PickingProcessLifecycleEventEntity[] getIterator()
 * @method PickingProcessLifecycleEventEntity[] getElements()
 * @method PickingProcessLifecycleEventEntity|null get(string $key)
 * @method PickingProcessLifecycleEventEntity|null first()
 * @method PickingProcessLifecycleEventEntity|null last()
 *
 * @extends EntityCollection<PickingProcessLifecycleEventEntity>
 */
class PickingProcessLifecycleEventCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingProcessLifecycleEventEntity::class;
    }
}
