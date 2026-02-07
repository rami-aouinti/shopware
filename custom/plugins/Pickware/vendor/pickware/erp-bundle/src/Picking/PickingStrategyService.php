<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;

class PickingStrategyService
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function selectLocationsToPickFrom(
        ProductQuantityLocationImmutableCollection $prioritizedStock,
        ProductQuantityImmutableCollection $productsToPick,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        $productQuantityLocationsToPickFrom = [];
        $stockShortages = [];
        foreach ($productsToPick as $productToPick) {
            $quantityLeftToPick = $productToPick->getQuantity();
            foreach ($prioritizedStock as $pickableStock) {
                if ($pickableStock->getProductId() !== $productToPick->getProductId()) {
                    continue;
                }
                $quantity = min($pickableStock->getQuantity(), $quantityLeftToPick);
                $productQuantityLocationsToPickFrom[] = new ProductQuantityLocation(
                    locationReference: $pickableStock->getStockLocationReference(),
                    productId: $productToPick->getProductId(),
                    quantity: $quantity,
                );
                $quantityLeftToPick -= $quantity;

                if ($quantityLeftToPick === 0) {
                    break;
                }
            }

            if ($quantityLeftToPick !== 0) {
                $stockShortages[] = new ProductQuantity(
                    productId: $productToPick->getProductId(),
                    quantity: $quantityLeftToPick,
                );
            }
        }

        if (count($stockShortages) > 0) {
            /** @var ProductCollection $products */
            $products = $this->entityManager->findBy(
                ProductDefinition::class,
                ['id' => array_map(fn(ProductQuantity $product) => $product->getProductId(), $stockShortages)],
                $context,
            );

            throw new PickingStrategyStockShortageException(
                stockShortages: ProductQuantityImmutableCollection::create($stockShortages),
                partialPickingRequestSolution: ProductQuantityLocationImmutableCollection::create(
                    $productQuantityLocationsToPickFrom,
                ),
                productNumbers: array_values(array_map(
                    fn($product) => $product->getProductNumber(),
                    $products->getElements(),
                )),
            );
        }

        return ProductQuantityLocationImmutableCollection::create($productQuantityLocationsToPickFrom);
    }
}
