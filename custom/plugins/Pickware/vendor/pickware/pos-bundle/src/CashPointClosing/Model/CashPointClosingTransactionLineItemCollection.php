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
 * @method void add(CashPointClosingTransactionLineItemEntity $entity)
 * @method void set(string $key, CashPointClosingTransactionLineItemEntity $entity)
 * @method CashPointClosingTransactionLineItemEntity[] getIterator()
 * @method CashPointClosingTransactionLineItemEntity[] getElements()
 * @method CashPointClosingTransactionLineItemEntity|null get(string $key)
 * @method CashPointClosingTransactionLineItemEntity|null first()
 * @method CashPointClosingTransactionLineItemEntity|null last()
 */
class CashPointClosingTransactionLineItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CashPointClosingTransactionLineItemEntity::class;
    }
}
