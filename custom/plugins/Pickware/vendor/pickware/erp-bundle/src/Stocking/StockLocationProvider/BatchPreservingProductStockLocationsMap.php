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
readonly class BatchPreservingProductStockLocationsMap implements BatchAwareStockLocationProvider
{
    /**
     * @var array<string, mixed> productIds as keys, values are ignored
     */
    private array $batchManagedProductIds;

    /**
     * @param array<string, StockLocationBatchInformation[]> $stockLocationsByProductId
     * @param string[] $batchManagedProductIds
     */
    public function __construct(
        private array $stockLocationsByProductId,
        array $batchManagedProductIds,
    ) {
        $this->batchManagedProductIds = array_flip($batchManagedProductIds);
    }

    public function getNextStockLocationForProduct(string $productId): ?StockLocationReference
    {
        $stockLocations = $this->stockLocationsByProductId[$productId] ?? [];
        if (array_key_exists($productId, $this->batchManagedProductIds)) {
            $nextStockLocation = array_find($stockLocations, fn(StockLocationBatchInformation $location) => $location->tryAddStockWithoutBatchInformation());
        } else {
            $nextStockLocation = array_first($stockLocations);
        }

        return $nextStockLocation?->getLocation();
    }

    public function getNextStockLocationForProductAndBatch(string $productId, string $batchId): ?StockLocationReference
    {
        $stockLocations = $this->stockLocationsByProductId[$productId] ?? [];
        if (array_key_exists($productId, $this->batchManagedProductIds)) {
            $nextStockLocation = array_find($stockLocations, fn(StockLocationBatchInformation $location) => $location->tryAddStockOfBatch($batchId));
        } else {
            $nextStockLocation = array_first($stockLocations);
        }

        return $nextStockLocation?->getLocation();
    }
}
