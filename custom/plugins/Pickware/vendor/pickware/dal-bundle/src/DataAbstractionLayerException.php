<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use LogicException;

class DataAbstractionLayerException extends LogicException
{
    public static function moreThanOneEntityInResultSet(string $methodName): self
    {
        return new self(sprintf(
            '%s was called with criteria that lead to more than one entity being contained in the result set.',
            $methodName,
        ));
    }

    public static function transactionNecessaryForPessimisticLocking(): self
    {
        return new self(sprintf(
            'An open transaction is required for pessimistic locking. Use %s::transactional to execute code in a ' .
            'transaction.',
            EntityManager::class,
        ));
    }
}
