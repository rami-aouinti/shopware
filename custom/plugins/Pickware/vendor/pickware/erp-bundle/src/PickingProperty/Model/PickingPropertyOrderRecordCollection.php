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
 * @method void add(PickingPropertyOrderRecordEntity $entity)
 * @method void set(string $key, PickingPropertyOrderRecordEntity $entity)
 * @method PickingPropertyOrderRecordEntity[] getIterator()
 * @method PickingPropertyOrderRecordEntity[] getElements()
 * @method PickingPropertyOrderRecordEntity|null get(string $key)
 * @method PickingPropertyOrderRecordEntity|null first()
 * @method PickingPropertyOrderRecordEntity|null last()
 */
class PickingPropertyOrderRecordCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingPropertyOrderRecordEntity::class;
    }
}
