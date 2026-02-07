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

use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Picking\PickableStockProvider;
use Pickware\PickwareErpStarter\Picking\WarehouseAndBinLocationPickableStockProvider;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessReservedItemCollection;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessReservedItemDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias('pickware_wms.default_pickable_stock_provider')]
class ReservedStockExcludingPickableStockProvider implements PickableStockProvider
{
    public function __construct(
        #[Autowire(service: WarehouseAndBinLocationPickableStockProvider::class)]
        private readonly PickableStockProvider $erpStockProvider,
        private readonly EntityManager $entityManager,
    ) {}

    public function getPickableStocks(
        array $productIds,
        ?array $warehouseIds,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        if ($warehouseIds === null) {
            $stockArea = StockArea::everywhere();
        } elseif (count($warehouseIds) === 1) {
            $stockArea = StockArea::warehouse($warehouseIds[0]);
        } elseif (method_exists(StockArea::class, 'warehouses')) {
            $stockArea = StockArea::warehouses($warehouseIds);
        } else {
            // Fallback behaviour, can be removed when the minimum required version for Pickware ERP Starter is 4.10.1 or higher.
            throw new LogicException('Warehouse IDs must have exactly one entry.');
        }

        // Every PickableStockProvider returns a `ProductQuantityLocationImmutableCollection` it's just the interface
        // that hasn't been updated because of compatibility reasons.
        /** @var ProductQuantityLocationImmutableCollection $erpPickableStocks */
        $erpPickableStocks = $this->erpStockProvider->getPickableStocks($productIds, $warehouseIds, $context);

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter(
                'locationType.technicalName',
                [
                    LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                    LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                ],
            ),
            new EqualsAnyFilter('productId', $productIds),
        );

        switch ($stockArea->getStockAreaType()) {
            case StockAreaType::Warehouse:
                $criteria->addFilter(
                    new MultiFilter('OR', [
                        new EqualsFilter('warehouseId', $stockArea->getWarehouseId()),
                        new EqualsFilter('binLocation.warehouseId', $stockArea->getWarehouseId()),
                    ]),
                );
                break;
            case StockAreaType::Warehouses:
                $criteria->addFilter(
                    new MultiFilter('OR', [
                        new EqualsAnyFilter('warehouseId', $stockArea->getWarehouseIds()),
                        new EqualsAnyFilter('binLocation.warehouseId', $stockArea->getWarehouseIds()),
                    ]),
                );
                break;
        }

        /** @var PickingProcessReservedItemCollection $reservedItems */
        $reservedItems = $this->entityManager->findBy(PickingProcessReservedItemDefinition::class, $criteria, $context);
        $reservedStock = $reservedItems->getProductQuantityLocations();

        return ProductQuantityLocationImmutableCollectionExtension
            ::subtract($erpPickableStocks, $reservedStock)
            ->filter(fn(ProductQuantityLocation $productQuantityLocation) => $productQuantityLocation->getQuantity() > 0);
    }
}
