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
 * @method void add(PickingPropertyDeliveryRecordEntity $entity)
 * @method void set(string $key, PickingPropertyDeliveryRecordEntity $entity)
 * @method PickingPropertyDeliveryRecordEntity[] getIterator()
 * @method PickingPropertyDeliveryRecordEntity[] getElements()
 * @method PickingPropertyDeliveryRecordEntity|null get(string $key)
 * @method PickingPropertyDeliveryRecordEntity|null first()
 * @method PickingPropertyDeliveryRecordEntity|null last()
 *
 * @extends EntityCollection<PickingPropertyDeliveryRecordEntity>
 */
class PickingPropertyDeliveryRecordCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingPropertyDeliveryRecordEntity::class;
    }
}
