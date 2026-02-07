<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(CashPointClosingTransactionEntity $entity)
 * @method void set(string $key, CashPointClosingTransactionEntity $entity)
 * @method CashPointClosingTransactionEntity[] getIterator()
 * @method CashPointClosingTransactionEntity[] getElements()
 * @method CashPointClosingTransactionEntity|null get(string $key)
 * @method CashPointClosingTransactionEntity|null first()
 * @method CashPointClosingTransactionEntity|null last()
 */
class CashPointClosingTransactionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CashPointClosingTransactionEntity::class;
    }
}
