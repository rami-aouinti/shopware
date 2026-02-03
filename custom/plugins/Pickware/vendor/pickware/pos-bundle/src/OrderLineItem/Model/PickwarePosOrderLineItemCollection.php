<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderLineItem\Model;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickwarePosOrderLineItemEntity $entity)
 * @method void set(string $key, PickwarePosOrderLineItemEntity $entity)
 * @method PickwarePosOrderLineItemEntity[] getIterator()
 * @method PickwarePosOrderLineItemEntity[] getElements()
 * @method PickwarePosOrderLineItemEntity|null get(string $key)
 * @method PickwarePosOrderLineItemEntity|null first()
 * @method PickwarePosOrderLineItemEntity|null last()
 */
class PickwarePosOrderLineItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderLineItemEntity::class;
    }
}
