<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking\StockLocationProvider;

use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class StockLocationBatchInformation
{
    /**
     * @param string[] $batchIds
     */
    public function __construct(
        private readonly StockLocationReference $location,
        private array $batchIds,
        private bool $hasStockWithoutBatchInformation,
    ) {}

    public function getLocation(): StockLocationReference
    {
        return $this->location;
    }

    /**
     * @return string[]
     */
    public function getBatchIds(): array
    {
        return $this->batchIds;
    }

    public function hasStockWithoutBatchInformation(): bool
    {
        return $this->hasStockWithoutBatchInformation;
    }

    public function tryAddStockWithoutBatchInformation(): bool
    {
        if (count($this->batchIds) > 0) {
            return false;
        }
        $this->hasStockWithoutBatchInformation = true;

        return true;
    }

    public function tryAddStockOfBatch(string $batchId): bool
    {
        if ($this->hasStockWithoutBatchInformation) {
            return false;
        }

        if (count($this->batchIds) === 0) {
            $this->batchIds[] = $batchId;

            return true;
        }

        return $this->batchIds === [$batchId];
    }
}
