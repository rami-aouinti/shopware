<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(CashRegisterEntity $entity)
 * @method void set(string $key, CashRegisterEntity $entity)
 * @method CashRegisterEntity[] getIterator()
 * @method CashRegisterEntity[] getElements()
 * @method CashRegisterEntity|null get(string $key)
 * @method CashRegisterEntity|null first()
 * @method CashRegisterEntity|null last()
 */
class CashRegisterCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CashRegisterEntity::class;
    }
}
