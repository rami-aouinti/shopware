<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Routing;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias('pickware_erp.default_routing_strategy')]
class BinLocationPositionRespectingRoutingStrategy implements RoutingStrategy
{
    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'pickware_erp.bin_location_position_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $sockLocationSorter,
    ) {}

    public function route(
        ProductQuantityLocationImmutableCollection $productQuantityLocations,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        $productIds = $productQuantityLocations
            ->map(fn(ProductQuantityLocation $productQuantityLocation) => $productQuantityLocation->getProductId())
            ->deduplicate()
            ->asArray();
        $products = $this->entityManager->findBy(ProductDefinition::class, ['id' => $productIds], $context);

        $deduplicatedStockLocations = [];
        $productQuantityLocations->forEach(
            function(ProductQuantityLocation $productQuantityLocation) use (&$deduplicatedStockLocations): void {
                $stockLocation = $productQuantityLocation->getStockLocationReference();
                $deduplicatedStockLocations[$stockLocation->hash()] ??= $stockLocation;
            },
        );
        $sortedStockLocations = $this->sockLocationSorter->sort(
            ImmutableCollection::create($deduplicatedStockLocations),
            $context,
        );

        return $productQuantityLocations->sorted(
            function(ProductQuantityLocation $lhs, ProductQuantityLocation $rhs) use ($sortedStockLocations, $products) {
                $lhsIndex = $sortedStockLocations->indexOfElementEqualTo($lhs->getStockLocationReference());
                $rhsIndex = $sortedStockLocations->indexOfElementEqualTo($rhs->getStockLocationReference());

                // Priority 1: Sort by the stock locations
                $sortingValue = $lhsIndex <=> $rhsIndex;
                if ($sortingValue !== 0) {
                    return $sortingValue;
                }

                // Priority 2: Product quantities with the same stock location are sorted by their product number
                $lhsProductNumber = $products->get($lhs->getProductId())?->getProductNumber() ?? '';
                $rhsProductNumber = $products->get($rhs->getProductId())?->getProductNumber() ?? '';

                return $lhsProductNumber <=> $rhsProductNumber;
            },
        );
    }
}
