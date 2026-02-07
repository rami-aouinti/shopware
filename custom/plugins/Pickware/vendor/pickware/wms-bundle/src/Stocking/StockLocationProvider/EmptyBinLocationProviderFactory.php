<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Stocking\StockLocationProvider;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProviderFactory;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EmptyBinLocationProviderFactory implements StockLocationProviderFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'pickware_wms.bin_location_property_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $stockLocationSorter,
    ) {}

    public function makeStockLocationProvider(
        ProductQuantityImmutableCollection $productQuantities,
        StockArea $stockArea,
        Context $context,
    ): ConsumingStockLocationList {
        $criteria = ['stocks.id' => null];
        match ($stockArea->getStockAreaType()) {
            StockAreaType::Warehouse => $criteria['warehouseId'] = $stockArea->getWarehouseId(),
            StockAreaType::Everywhere => null,
        };
        $emptyBinLocationReferences = ImmutableCollection
            ::create($this->entityManager->findBy(
                BinLocationDefinition::class,
                $criteria,
                $context,
            ))
            ->map(fn(BinLocationEntity $binLocation) => StockLocationReference::binLocation($binLocation->getId()));

        return new ConsumingStockLocationList(
            prioritizedStockLocationReferences: $this->stockLocationSorter->sort(
                stockLocationReferences: $emptyBinLocationReferences,
                context: $context,
            ),
        );
    }
}
