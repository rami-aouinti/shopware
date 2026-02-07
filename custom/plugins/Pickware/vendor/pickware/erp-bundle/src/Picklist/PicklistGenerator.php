<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Pickware\PickwareErpStarter\Picking\PickableStockProvider;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PicklistGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator,
        private readonly PickableStockProvider $pickableStockProvider,
        #[Autowire(service: 'pickware_erp.bin_location_code_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $stockLocationSorter,
    ) {}

    /**
     * @return ImmutableCollection<PicklistEntry>
     */
    public function generatePicklist(string $orderId, string $warehouseId, Context $context): ImmutableCollection
    {
        $productsToShip = $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrder(
            orderId: $orderId,
            context: $context,
        );
        $pickableStock = $this->pickableStockProvider
            ->getPickableStocks(
                $productsToShip->getProductIds()->asArray(),
                [$warehouseId],
                $context,
            )
            ->filter(
                fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()->isBinLocation(),
            );

        $binLocationIds = $pickableStock
            ->map(fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()->getBinLocationId())
            ->asArray();
        $binLocations = $this->entityManager->findBy(BinLocationDefinition::class, ['id' => $binLocationIds], $context);
        /** @var ProductCollection $products */
        $products = $this->entityManager->findBy(
            ProductDefinition::class,
            ['id' => $productsToShip->getProductIds()->asArray()],
            $context,
        );

        // Step 2: Sort the pickable stock by stock locations to prioritize for picking
        $sortedStockLocations = $this->stockLocationSorter->sort(
            stockLocationReferences: $pickableStock->map(
                fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference(),
            ),
            context: $context,
        );
        // Apply stock location sorting to pickable stock
        $sortedPickableStock = $pickableStock->sorted(
            function(ProductQuantityLocation $lhs, ProductQuantityLocation $rhs) use ($sortedStockLocations) {
                $lhsIndex = $sortedStockLocations->indexOfElementEqualTo($lhs->getStockLocationReference());
                $rhsIndex = $sortedStockLocations->indexOfElementEqualTo($rhs->getStockLocationReference());

                return $lhsIndex <=> $rhsIndex;
            },
        );

        // Step 4: Create picklist entries and sort them.
        return $productsToShip
            ->map(fn(ProductQuantity $productQuantity) => new PicklistEntry(
                productId: $productQuantity->getProductId(),
                quantity: $productQuantity->getQuantity(),
                binLocationCodes: $sortedPickableStock
                    ->filter(
                        fn(ProductQuantityLocation $stock) => $stock->getProductId() === $productQuantity->getProductId(),
                    )
                    ->map(
                        fn(ProductQuantityLocation $stock) => $binLocations
                            ->get($stock->getStockLocationReference()->getBinLocationId())
                            ->getCode(),
                    )
                    ->asArray(),
            ))
            ->sorted(function(PicklistEntry $lhs, PicklistEntry $rhs) use ($products) {
                // Priority 1: Sort by first bin location code
                if (($lhs->getBinLocationCodes()[0] ?? '') !== ($rhs->getBinLocationCodes()[0] ?? '')) {
                    return strcasecmp($lhs->getBinLocationCodes()[0] ?? '', ($rhs->getBinLocationCodes()[0] ?? ''));
                }
                // Priority 2: Sort by product number
                $lhsProductNumber = $products->get($lhs->getProductId())?->getProductNumber() ?? '';
                $rhsProductNumber = $products->get($rhs->getProductId())?->getProductNumber() ?? '';

                return strcasecmp($lhsProductNumber, $rhsProductNumber);
            });
    }
}
