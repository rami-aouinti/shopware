<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\ExceptionHandling;

use Doctrine\DBAL\Exception as DBALException;
use Exception;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Throwable;

/**
 * Handles primary key constraint violation exceptions thrown by Doctrine DBAL and maps them to UniqueIndexHttpException
 * exceptions.
 * Without this handler, integrity constraint violations would generate error messages that are unique for each primary
 * key (ID) and, therefore, cannot be merged and tracked properly. With this handler, these exception messages only
 * contain the primary key identifier (e.g. "table.PRIMARY") so they can be grouped together.
 */
class PrimaryKeyConstraintExceptionHandler implements ExceptionHandlerInterface
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

        $indexViolationPattern = '/^.*SQLSTATE\\[23000\\]: Integrity constraint violation: 1062 Duplicate entry \'.*\' for key.*PRIMARY/';
        if (preg_match($indexViolationPattern, $e->getMessage())) {
            $pattern = "/for key '(?P<identifier>[^']+)'/";

            $matches = [];
            $identifier = '{NOT_FOUND}';
            if (preg_match($pattern, $e->getMessage(), $matches)) {
                $identifier = $matches['identifier'];
            }

            return UniqueIndexHttpException::createForPrimaryKeyConstraintViolation($identifier, $e);
        }

        return null;
    }
}
