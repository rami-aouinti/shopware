<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockReservation;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;

class ProductQuantityLocationImmutableCollectionExtension
{
    public static function add(
        ProductQuantityLocationImmutableCollection $summand1,
        ProductQuantityLocationImmutableCollection $summand2,
    ): ProductQuantityLocationImmutableCollection {
        $sums = [];
        $stockLocations = [];
        foreach ($summand1 as $element) {
            $locationHash = $element->getStockLocationReference()->hash();
            $stockLocations[$locationHash] ??= $element->getStockLocationReference();
            $sums[$element->getProductId()][$locationHash] ??= 0;
            $sums[$element->getProductId()][$locationHash] += $element->getQuantity();
        }
        foreach ($summand2 as $element) {
            $locationHash = $element->getStockLocationReference()->hash();
            $stockLocations[$locationHash] ??= $element->getStockLocationReference();
            $sums[$element->getProductId()][$locationHash] ??= 0;
            $sums[$element->getProductId()][$locationHash] += $element->getQuantity();
        }

        $results = [];
        foreach ($sums as $productId => $sum) {
            foreach ($sum as $locationHash => $quantity) {
                $results[] = new ProductQuantityLocation(
                    $stockLocations[$locationHash],
                    $productId,
                    $quantity,
                );
            }
        }

        return new ProductQuantityLocationImmutableCollection($results);
    }

    public static function subtract(
        ProductQuantityLocationImmutableCollection $minuend,
        ProductQuantityLocationImmutableCollection $subtrahend,
    ): ProductQuantityLocationImmutableCollection {
        return self::add($minuend, self::negate($subtrahend));
    }

    private static function negate(
        ProductQuantityLocationImmutableCollection $collection,
    ): ProductQuantityLocationImmutableCollection {
        return $collection->map(
            fn(ProductQuantityLocation $productQuantityLocation) => new ProductQuantityLocation(
                $productQuantityLocation->getStockLocationReference(),
                $productQuantityLocation->getProductId(),
                - $productQuantityLocation->getQuantity(),
            ),
            ProductQuantityLocationImmutableCollection::class,
        );
    }
}
