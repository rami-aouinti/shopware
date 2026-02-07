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
 * @method void add(ReturnOrderRefundEntity $entity)
 * @method void set(string $key, ReturnOrderRefundEntity $entity)
 * @method ReturnOrderRefundEntity[] getIterator()
 * @method ReturnOrderRefundEntity[] getElements()
 * @method ReturnOrderRefundEntity|null get(string $key)
 * @method ReturnOrderRefundEntity|null first()
 * @method ReturnOrderRefundEntity|null last()
 */
class ReturnOrderRefundCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ReturnOrderRefundEntity::class;
    }
}
