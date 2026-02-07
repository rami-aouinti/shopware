<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProperty\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickingPropertyDeliveryRecordValueEntity $entity)
 * @method void set(string $key, PickingPropertyDeliveryRecordValueEntity $entity)
 * @method PickingPropertyDeliveryRecordValueEntity[] getIterator()
 * @method PickingPropertyDeliveryRecordValueEntity[] getElements()
 * @method PickingPropertyDeliveryRecordValueEntity|null get(string $key)
 * @method PickingPropertyDeliveryRecordValueEntity|null first()
 * @method PickingPropertyDeliveryRecordValueEntity|null last()
 *
 * @extends EntityCollection<PickingPropertyDeliveryRecordValueEntity>
 */
class PickingPropertyDeliveryRecordValueCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingPropertyDeliveryRecordValueEntity::class;
    }
}
