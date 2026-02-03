<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use InvalidArgumentException;
use Shopware\Core\Framework\Context;

class ReservedStockCalculationExtensionEvent
{
    /**
     * @var array<string>
     */
    private array $additionalJoins = [];

    /**
     * @var array<string>
     */
    private array $additionalWhereConditions = [];

    private Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Consider introducing an alias when joining a table so you can make sure you are referencing the table with the
     * correct ON clause in any WHERE condition you want to add
     * @param string $join SQL JOIN expression
     */
    public function addJoin(string $join): void
    {
        $this->additionalJoins[] = $join;
    }

    public function getAdditionalJoinsSQL(): string
    {
        if (empty($this->additionalJoins)) {
            return '';
        }

        return "\n" . implode("\n", $this->additionalJoins);
    }

    /**
     * Conditions added here are allowed to reference any table queried in the @see InternalReservedStockUpdater and
     * ones you joined with an additional join, please do not rely on a table joined by another event listener
     * @param string $whereCondition SQL WHERE condition
     */
    public function addWhereCondition(string $whereCondition): void
    {
        if (str_starts_with($whereCondition, 'WHERE')) {
            throw new InvalidArgumentException('The WHERE clause is already in place, please only add additional conditions');
        }

        $this->additionalWhereConditions[] = $whereCondition;
    }

    public function getAdditionalWhereConditionsSQL(): string
    {
        if (empty($this->additionalWhereConditions)) {
            return '';
        }

        return "\nAND " . implode("\nAND ", $this->additionalWhereConditions);
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
