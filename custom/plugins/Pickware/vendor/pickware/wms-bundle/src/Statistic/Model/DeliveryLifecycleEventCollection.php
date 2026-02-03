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
 * @method void add(DeliveryLifecycleEventEntity $entity)
 * @method void set(string $key, DeliveryLifecycleEventEntity $entity)
 * @method DeliveryLifecycleEventEntity[] getIterator()
 * @method DeliveryLifecycleEventEntity[] getElements()
 * @method DeliveryLifecycleEventEntity|null get(string $key)
 * @method DeliveryLifecycleEventEntity|null first()
 * @method DeliveryLifecycleEventEntity|null last()
 *
 * @extends EntityCollection<DeliveryLifecycleEventEntity>
 */
class DeliveryLifecycleEventCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DeliveryLifecycleEventEntity::class;
    }
}
