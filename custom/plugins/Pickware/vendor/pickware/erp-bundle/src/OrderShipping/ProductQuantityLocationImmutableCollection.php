<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\BatchQuantityLocation;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;

/**
 * @extends ImmutableCollection<ProductQuantityLocation>
 */
class ProductQuantityLocationImmutableCollection extends ImmutableCollection
{
    public function groupByProductId(): ProductQuantityImmutableCollection
    {
        return $this
            ->map(
                fn(ProductQuantityLocation $stock) => new ProductQuantity(
                    $stock->getProductId(),
                    $stock->getQuantity(),
                    $stock->getBatches(),
                ),
                ProductQuantityImmutableCollection::class,
            )
            ->groupByProductId();
    }

    /**
     * @return ImmutableCollection<BatchQuantityLocation>
     */
    public function asBatchQuantityLocations(): ImmutableCollection
    {
        return $this->flatMap(fn(ProductQuantityLocation $stock) => $stock->asBatchQuantityLocations());
    }

    /**
     * @param array<string, mixed> $stockMovementMetaData
     * @return StockMovement[]
     */
    public function createStockMovementsWithSource(
        StockLocationReference $sourceLocation,
        array $stockMovementMetaData = [],
    ): array {
        return $this
            ->map(fn(ProductQuantityLocation $productQuantityLocation) => StockMovement::create(array_merge(
                $stockMovementMetaData,
                [
                    'productId' => $productQuantityLocation->getProductId(),
                    'quantity' => $productQuantityLocation->getQuantity(),
                    'source' => $sourceLocation,
                    'destination' => $productQuantityLocation->getStockLocationReference(),
                    'batches' => $productQuantityLocation->getBatches()?->asCountingMap(),
                ],
            )))
            ->asArray();
    }

    /**
     * @param array<string, mixed> $stockMovementMetaData
     * @return StockMovement[]
     */
    public function createStockMovementsWithDestination(
        StockLocationReference $destinationLocation,
        array $stockMovementMetaData = [],
    ): array {
        return $this
            ->map(fn(ProductQuantityLocation $productQuantityLocation) => StockMovement::create(array_merge(
                $stockMovementMetaData,
                [
                    'productId' => $productQuantityLocation->getProductId(),
                    'quantity' => $productQuantityLocation->getQuantity(),
                    'source' => $productQuantityLocation->getStockLocationReference(),
                    'destination' => $destinationLocation,
                    'batches' => $productQuantityLocation->getBatches()?->asCountingMap(),
                ],
            )))
            ->asArray();
    }

    /**
     * @param ImmutableCollection<StockLocationReference> $sortedStockLocationReferences
     */
    public function sortLike(ImmutableCollection $sortedStockLocationReferences): self
    {
        return $this->sorted(
            function(ProductQuantityLocation $lhs, ProductQuantityLocation $rhs) use ($sortedStockLocationReferences) {
                $lhsIndex = $sortedStockLocationReferences->indexOfElementEqualTo($lhs->getStockLocationReference());
                $rhsIndex = $sortedStockLocationReferences->indexOfElementEqualTo($rhs->getStockLocationReference());

                return $lhsIndex <=> $rhsIndex;
            },
        );
    }

    public static function fromArray(array $array, ?string $class = ProductQuantityLocation::class): static
    {
        return parent::fromArray($array, $class);
    }
}
