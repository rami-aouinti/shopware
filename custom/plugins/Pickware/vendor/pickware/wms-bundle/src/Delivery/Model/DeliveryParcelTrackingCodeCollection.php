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
 * @method void add(DeliveryParcelTrackingCodeEntity $entity)
 * @method void set(string $key, DeliveryParcelTrackingCodeEntity $entity)
 * @method DeliveryParcelTrackingCodeEntity[] getIterator()
 * @method DeliveryParcelTrackingCodeEntity[] getElements()
 * @method DeliveryParcelTrackingCodeEntity|null get(string $key)
 * @method DeliveryParcelTrackingCodeEntity|null first()
 * @method DeliveryParcelTrackingCodeEntity|null last()
 *
 * @extends EntityCollection<DeliveryParcelTrackingCodeEntity>
 */
class DeliveryParcelTrackingCodeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DeliveryParcelTrackingCodeEntity::class;
    }
}
