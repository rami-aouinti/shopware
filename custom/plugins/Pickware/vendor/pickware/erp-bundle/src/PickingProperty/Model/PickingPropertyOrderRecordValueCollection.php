<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickingPropertyOrderRecordValueEntity $entity)
 * @method void set(string $key, PickingPropertyOrderRecordValueEntity $entity)
 * @method PickingPropertyOrderRecordValueEntity[] getIterator()
 * @method PickingPropertyOrderRecordValueEntity[] getElements()
 * @method PickingPropertyOrderRecordValueEntity|null get(string $key)
 * @method PickingPropertyOrderRecordValueEntity|null first()
 * @method PickingPropertyOrderRecordValueEntity|null last()
 */
class PickingPropertyOrderRecordValueCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingPropertyOrderRecordValueEntity::class;
    }
}
