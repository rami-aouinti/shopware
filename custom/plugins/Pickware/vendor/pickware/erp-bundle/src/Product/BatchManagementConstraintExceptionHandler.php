<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product;

use Doctrine\DBAL\Exception as DBALException;
use Exception;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Throwable;

class BatchManagementConstraintExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(Throwable $e): ?Exception
    {
        if (!$e instanceof DBALException) {
            return null;
        }

        // SQLSTATE[HY000]: General error: 3819 Check constraint 'batch_managed_requires_stock_management' is violated.
        $constraintViolationPatternMySql = '/SQLSTATE\\[HY000\\]:.*3819.*Check constraint \'batch_managed_requires_stock_management\' is violated\\./';
        // SQLSTATE[23000]: Integrity constraint violation: 4025 CONSTRAINT `batch_managed_requires_stock_management` failed for `db_name`
        $constraintViolationPatternMariaDb = '/SQLSTATE\\[23000\\]:.*4025 CONSTRAINT `batch_managed_requires_stock_management` failed/';

        if (
            preg_match($constraintViolationPatternMySql, $e->getMessage())
            || preg_match($constraintViolationPatternMariaDb, $e->getMessage())
        ) {
            return BatchManagementConstraintViolationException::batchManagementCannotBeEnabledWhenStockManagementIsDisabled(
                $e,
            );
        }

        return null;
    }
}
