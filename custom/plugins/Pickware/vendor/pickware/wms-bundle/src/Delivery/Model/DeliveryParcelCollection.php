<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(DeliveryParcelEntity $entity)
 * @method void set(string $key, DeliveryParcelEntity $entity)
 * @method DeliveryParcelEntity[] getIterator()
 * @method DeliveryParcelEntity[] getElements()
 * @method DeliveryParcelEntity|null get(string $key)
 * @method DeliveryParcelEntity|null first()
 * @method DeliveryParcelEntity|null last()
 *
 * @extends EntityCollection<DeliveryParcelEntity>
 */
class DeliveryParcelCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DeliveryParcelEntity::class;
    }
}
