<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(ReturnOrderLineItemEntity $entity)
 * @method void set(string $key, ReturnOrderLineItemEntity $entity)
 * @method ReturnOrderLineItemEntity[] getIterator()
 * @method ReturnOrderLineItemEntity[] getElements()
 * @method ReturnOrderLineItemEntity|null get(string $key)
 * @method ReturnOrderLineItemEntity|null first()
 * @method ReturnOrderLineItemEntity|null last()
 *
 * @extends EntityCollection<ReturnOrderLineItemEntity>
 */
class ReturnOrderLineItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ReturnOrderLineItemEntity::class;
    }
}
