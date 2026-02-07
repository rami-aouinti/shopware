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

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\RetryableException;

/**
 * @deprecated Will be removed with 6.0.0. Use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction instead.
 * The RetryableTransaction from Shopware was a copy of this class and contains a workaround for a bug in Doctrine DBAL
 * as of Shopware 6.7.0.0. Because of this we decided to use Shopwares implementation of our own.
 * Further references: https://github.com/pickware/shopware-plugins/issues/9024
 */
class RetryableTransaction
{
    /**
     * Executes the given closure inside a DBAL transaction. In case of a deadlock (RetryableException) the transaction
     * is rolled back and the closure will be retried. Because it may run multiple times the closure should not cause
     * any side effects outside of its own scope.
     */
    public static function retryable(Connection $connection, Closure $closure)
    {
        return self::retry($connection, $closure, 1);
    }

    private static function retry(Connection $connection, Closure $closure, int $attemptCounter)
    {
        try {
            return $connection->transactional($closure);
        } catch (RetryableException $retryableException) {
            if ($connection->getTransactionNestingLevel() > 0) {
                // If this RetryableTransaction was executed inside another transaction, do not retry this nested
                // transaction. Remember that the whole (outermost) transaction was already rolled back by the database
                // when any RetryableException is thrown.
                // Rethrow the exception here so only the outermost transaction is retried which in turn includes this
                // nested transaction.
                throw $retryableException;
            }

            if ($attemptCounter > 10) {
                throw $retryableException;
            }

            return self::retry($connection, $closure, $attemptCounter + 1);
        }
    }
}
