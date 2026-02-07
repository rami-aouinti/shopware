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

use LogicException;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\BatchQuantity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;

/**
 * @extends ImmutableCollection<ProductQuantity>
 */
class ProductQuantityImmutableCollection extends ImmutableCollection
{
    public function groupByProductId(): ProductQuantityImmutableCollection
    {
        // Old versions of the Pickware\PickwareWms\StockingProcess\Model\StockingProcessSourceCollection incorrectly
        // instantiate this collection with ProductQuantityLocations. We need to convert them to ProductQuantities.
        // Can be removed as soon as WMS requires at least version 4.36.x of ERP-Starter.
        // @phpstan-ignore-next-line instanceof.alwaysTrue
        if ($this->containsElementSatisfying(fn(ProductQuantity|ProductQuantityLocation $element) => $element instanceof ProductQuantityLocation)) {
            trigger_error('Instantiating this collection with ProductQuantityLocations is deprecated. Make sure to use ProductQuantity instances only.', E_USER_DEPRECATED);

            $productQuantities = $this->map(fn(ProductQuantity|ProductQuantityLocation $element) => new ProductQuantity($element->getProductId(), $element->getQuantity()));
        } else {
            $productQuantities = $this;
        }

        return new self($productQuantities->groupBy(
            fn(ProductQuantity $productQuantity) => $productQuantity->getProductId(),
            function(ImmutableCollection $productQuantities): ProductQuantity {
                $combinedProductQuantity = new ProductQuantity($productQuantities->first()->getProductId(), 0);
                foreach ($productQuantities as $productQuantity) {
                    $combinedProductQuantity = $combinedProductQuantity->add($productQuantity);
                }

                return $combinedProductQuantity;
            },
        ));
    }

    /**
     * @deprecated Because this method groups everytime, the performance of this method is bad. Instead group first,
     *     save the result and then filter by yourself.
     */
    public function findQuantityByProductId(string $productId): ?int
    {
        return $this
            ->groupByProductId()
            ->filter(fn(ProductQuantity $productQuantity) => $productQuantity->getProductId() === $productId)
            ->first()
            ?->getQuantity();
    }

    /**
     * @return ImmutableCollection<string>
     */
    public function getProductIds(): ImmutableCollection
    {
        return $this
            ->groupByProductId()
            ->map(fn(ProductQuantity $productQuantity) => $productQuantity->getProductId());
    }

    public function subtract(ProductQuantityImmutableCollection $subtrahend): self
    {
        return $subtrahend
            ->map(
                fn(ProductQuantity $productQuantity) => $productQuantity->negate(),
                static::class,
            )
            ->merge($this)
            ->groupByProductId();
    }

    /**
     * @return ImmutableCollection<StockMovement>
     */
    public function createStockMovements(
        StockLocationReference $source,
        StockLocationReference $destination,
    ): ImmutableCollection {
        return $this->groupByProductId()
            ->filter(fn(ProductQuantity $productQuantity) => $productQuantity->getQuantity() !== 0)
            ->map(fn(ProductQuantity $productQuantity) => StockMovement::create([
                'productId' => $productQuantity->getProductId(),
                'quantity' => $productQuantity->getQuantity(),
                'source' => $source,
                'destination' => $destination,
                'batches' => $productQuantity->getBatches()?->asCountingMap(),
            ]));
    }

    public static function fromArray(array $array, ?string $class = ProductQuantity::class): static
    {
        return parent::fromArray($array, $class);
    }

    /**
     * Returns a new ProductQuantityImmutableCollection that contains the minimum quantity for each product that is
     * contained in any of the given ProductQuantityImmutableCollections. It does not group each collection by product
     * before calculating the minimum quantity. Discards any batch information.
     * All collections must contain the same products, otherwise an exception is thrown.
     **/
    public static function min(
        ProductQuantityImmutableCollection ...$productQuantityCollections,
    ): ProductQuantityImmutableCollection {
        $productIds = ImmutableCollection::create($productQuantityCollections)
            ->flatMap(fn(ProductQuantityImmutableCollection $collection) => $collection->getProductIds())
            ->deduplicate();

        $minQuantitiesByProductId = [];
        foreach ($productQuantityCollections as $productQuantityCollection) {
            foreach ($productIds as $productId) {
                $productQuantitiesForProductId = $productQuantityCollection
                    ->filter(fn(ProductQuantity $productQuantity) => $productQuantity->getProductId() === $productId)
                    ->map(fn(ProductQuantity $productQuantity) => $productQuantity->getQuantity());
                if ($productQuantitiesForProductId->isEmpty()) {
                    throw new LogicException('Expected all product quantity collections to contain the same products, but the product with id %s is not present in all collections');
                }

                $quantityForProductId = $productQuantitiesForProductId->reduce(PHP_INT_MAX, min(...));
                $minQuantitiesByProductId[$productId] = min($minQuantitiesByProductId[$productId] ?? PHP_INT_MAX, $quantityForProductId);
            }
        }

        $minProductQuantities = [];
        foreach ($minQuantitiesByProductId as $productId => $quantity) {
            $minProductQuantities[] = new ProductQuantity($productId, $quantity);
        }

        return new ProductQuantityImmutableCollection($minProductQuantities);
    }

    /**
     * @return CountingMap<string> A map of product IDs to their total quantities
     */
    public function asCountingMap(): CountingMap
    {
        $grouped = $this->groupByProductId();

        if ($grouped->first(fn(ProductQuantity $productQuantity) => $productQuantity->getQuantity() < 0) !== null) {
            throw new LogicException(
                'Cannot convert to CountingMap because the total quantity for at least one product is negative.',
            );
        }

        return CountingMap::fromTuples($grouped->map(fn(ProductQuantity $productQuantity) => [
            $productQuantity->getProductId(),
            $productQuantity->getQuantity(),
        ])->asArray());
    }

    /**
     * @return ImmutableCollection<BatchQuantity>
     */
    public function asBatchQuantities(): ImmutableCollection
    {
        return $this->flatMap(fn(ProductQuantity $productQuantity) => $productQuantity->asBatchQuantities());
    }
}
