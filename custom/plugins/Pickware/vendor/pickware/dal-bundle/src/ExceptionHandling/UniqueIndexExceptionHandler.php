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
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
class UniqueIndexExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @var UniqueIndexExceptionMapping[]
     */
    private array $uniqueIndexExceptionMappings;

    public function __construct(array $uniqueIndexExceptionMappings)
    {
        $this->uniqueIndexExceptionMappings = $uniqueIndexExceptionMappings;
    }

    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(Throwable $e): ?Exception
    {
        if (!$e instanceof DBALException) {
            return null;
        }

        foreach ($this->uniqueIndexExceptionMappings as $uniqueIndexExceptionMapping) {
            $indexViolationPattern = sprintf(
                '/SQLSTATE\\[23000\\]:.*1062 Duplicate entry .*%s.*/',
                $uniqueIndexExceptionMapping->getUniqueIndexName(),
            );
            if (preg_match($indexViolationPattern, $e->getMessage())) {
                return UniqueIndexHttpException::create(
                    $uniqueIndexExceptionMapping,
                    $e,
                );
            }
        }

        return null;
    }
}
