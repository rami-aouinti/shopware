<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\Translation;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use RuntimeException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;

class StockLocationConfigurationService
{
    public function __construct(private readonly EntityManager $entityManager) {}

    /**
     * Returns the configurations for the passed stock locations.
     *
     * These configurations contain all information for a stock location that are not defined statically by its type but
     * are user-configurable.
     *
     * Feel free to extend this method to support more stock locations and also more configurations.
     *
     * @param ImmutableCollection<StockLocationReference> $stockLocations
     */
    public function getStockLocationConfigurations(
        ImmutableCollection $stockLocations,
        Context $context,
    ): StockLocationConfigurations {
        $warehouseIds = $stockLocations->compactMap(
            fn(StockLocationReference $stockLocation) => $stockLocation->isWarehouse() ? $stockLocation->getWarehouseId() : null,
        );
        $warehouses = new WarehouseCollection([]);
        if ($warehouseIds->count() > 0) {
            $warehouses = $this->entityManager->findBy(
                WarehouseDefinition::class,
                ['id' => $warehouseIds->asArray()],
                $context,
            );
        }
        $binLocationIds = $stockLocations->compactMap(
            fn(StockLocationReference $stockLocation)
            => $stockLocation->isBinLocation() ? $stockLocation->getBinLocationId() : null,
        );
        $binLocations = new BinLocationCollection([]);
        if ($binLocationIds->count() > 0) {
            $binLocations = $this->entityManager->findBy(
                BinLocationDefinition::class,
                ['id' => $binLocationIds->asArray()],
                $context,
                ['warehouse'],
            );
        }

        $returnOrderIds = $stockLocations->compactMap(
            fn(StockLocationReference $stockLocation) => $stockLocation->isReturnOrder() ? $stockLocation->getReturnOrderId() : null,
        );
        $returnOrders = new ReturnOrderCollection([]);
        if ($returnOrderIds->count() > 0) {
            $returnOrders = $this->entityManager->findBy(
                ReturnOrderDefinition::class,
                ['id' => $returnOrderIds->asArray()],
                $context,
            );
        }

        $orderIds = $stockLocations->compactMap(
            fn(StockLocationReference $stockLocation) => $stockLocation->isOrder() ? $stockLocation->getOrderId() : null,
        );
        $orders = new OrderCollection([]);
        if ($orderIds->count() > 0) {
            $orders = $this->entityManager->findBy(
                OrderDefinition::class,
                ['id' => $orderIds->asArray()],
                $context,
            );
        }

        $stockContainerIds = $stockLocations->compactMap(
            fn(StockLocationReference $stockLocation) => $stockLocation->isStockContainer() ? $stockLocation->getStockContainerId() : null,
        );
        $stockContainers = new StockContainerCollection([]);
        if ($stockContainerIds->count() > 0) {
            $stockContainers = $this->entityManager->findBy(
                StockContainerDefinition::class,
                ['id' => $stockContainerIds->asArray()],
                $context,
                ['warehouse'],
            );
        }

        $goodsReceiptIds = $stockLocations->compactMap(
            fn(StockLocationReference $stockLocation) => $stockLocation->isGoodsReceipt() ? $stockLocation->getGoodsReceiptId() : null,
        );
        $goodsReceipts = new GoodsReceiptCollection([]);
        if ($goodsReceiptIds->count() > 0) {
            $goodsReceipts = $this->entityManager->findBy(
                GoodsReceiptDefinition::class,
                ['id' => $goodsReceiptIds->asArray()],
                $context,
                ['warehouse'],
            );
        }

        $fail = fn() => throw new RuntimeException('Stock location not loaded.');

        $configurations = new StockLocationConfigurations();
        foreach ($stockLocations as $stockLocation) {
            switch ($stockLocation->getLocationTypeTechnicalName()) {
                case LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE:
                    $warehouse = $warehouses->get($stockLocation->getWarehouseId()) ?? $fail();
                    $configurations->addConfiguration(
                        $stockLocation,
                        new StockLocationConfiguration(
                            stockAvailableForSale: $warehouse->isIsStockAvailableForSale(),
                            code: 'unknown',
                            warehouseCode: $warehouse->getCode(),
                            position: null,
                            isInDefaultWarehouse: $warehouse->getIsDefault(),
                            warehouseCreationDate: $warehouse->getCreatedAt(),
                            globalUniqueDisplayName: new Translation(
                                german: sprintf('Unbekannter Ort in Lager %s (%s)', $warehouse->getName(), $warehouse->getCode()),
                                english: sprintf('Unknown location in warehouse %s (%s)', $warehouse->getName(), $warehouse->getCode()),
                            ),
                        ),
                    );
                    break;
                case LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION:
                    $binLocation = $binLocations->get($stockLocation->getBinLocationId()) ?? $fail();
                    $configurations->addConfiguration(
                        $stockLocation,
                        new StockLocationConfiguration(
                            stockAvailableForSale: $binLocation->getWarehouse()->isIsStockAvailableForSale(),
                            code: $binLocation->getCode(),
                            warehouseCode: $binLocation->getWarehouse()->getCode(),
                            position: $binLocation->getPosition(),
                            isInDefaultWarehouse: $binLocation->getWarehouse()->getIsDefault(),
                            warehouseCreationDate: $binLocation->getWarehouse()->getCreatedAt(),
                            globalUniqueDisplayName: new Translation(
                                german: sprintf('Lagerplatz %s/%s', $binLocation->getWarehouse()->getCode(), $binLocation->getCode()),
                                english: sprintf('Bin location %s/%s', $binLocation->getWarehouse()->getCode(), $binLocation->getCode()),
                            ),
                        ),
                    );
                    break;
                case LocationTypeDefinition::TECHNICAL_NAME_ORDER:
                    $orderNumber = ($orders->get($stockLocation->getOrderId()) ?? $fail())->getOrderNumber();
                    $configurations->addConfiguration(
                        $stockLocation,
                        new StockLocationConfiguration(
                            stockAvailableForSale: null,
                            code: $orderNumber,
                            warehouseCode: null,
                            position: null,
                            isInDefaultWarehouse: null,
                            warehouseCreationDate: null,
                            globalUniqueDisplayName: new Translation(
                                german: sprintf('Bestellung %s', $orderNumber ?? 'ohne Nummer'),
                                english: sprintf('Order %s', $orderNumber ?? 'without number'),
                            ),
                        ),
                    );
                    break;
                case LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER:
                    $returnOrderNumber = ($returnOrders->get($stockLocation->getReturnOrderId()) ?? $fail())->getNumber();
                    $configurations->addConfiguration(
                        $stockLocation,
                        new StockLocationConfiguration(
                            stockAvailableForSale: null,
                            code: $returnOrderNumber,
                            warehouseCode: null,
                            position: null,
                            isInDefaultWarehouse: null,
                            warehouseCreationDate: null,
                            globalUniqueDisplayName: new Translation(
                                german: sprintf('Retoure %s', $returnOrderNumber),
                                english: sprintf('Return order %s', $returnOrderNumber),
                            ),
                        ),
                    );
                    break;
                case LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER:
                    $stockContainer = $stockContainers->get($stockLocation->getStockContainerId()) ?? $fail();
                    $stockContainerNumber = $stockContainer->getNumber();
                    $configurations->addConfiguration(
                        $stockLocation,
                        new StockLocationConfiguration(
                            stockAvailableForSale: $stockContainer->getWarehouse()->isIsStockAvailableForSale(),
                            code: $stockContainerNumber,
                            warehouseCode: $stockContainer->getWarehouse()->getCode(),
                            position: null,
                            isInDefaultWarehouse: $stockContainer->getWarehouse()->getIsDefault(),
                            warehouseCreationDate: $stockContainer->getWarehouse()->getCreatedAt(),
                            globalUniqueDisplayName: new Translation(
                                german: 'Kommissionierkiste ' . ($stockContainerNumber ?? 'ohne Nummer'),
                                english: 'Picking box ' . ($stockContainerNumber ?? 'without number'),
                            ),
                        ),
                    );
                    break;
                case LocationTypeDefinition::TECHNICAL_NAME_GOODS_RECEIPT:
                    $goodsReceipt = $goodsReceipts->get($stockLocation->getGoodsReceiptId()) ?? $fail();
                    $goodsReceiptNumber = $goodsReceipt->getNumber();
                    $warehouse = $goodsReceipt->getWarehouse();
                    $configurations->addConfiguration(
                        $stockLocation,
                        new StockLocationConfiguration(
                            stockAvailableForSale: $warehouse?->isIsStockAvailableForSale() ?? false,
                            code: $goodsReceiptNumber,
                            warehouseCode: $warehouse?->getCode() ?? $goodsReceipt->getWarehouseSnapshot()['code'] ?? 'deleted',
                            position: null,
                            isInDefaultWarehouse: $warehouse?->getIsDefault() ?? false,
                            warehouseCreationDate: $warehouse?->getCreatedAt() ?? new DateTime('@0'),
                            globalUniqueDisplayName: new Translation(
                                german: sprintf('Wareneingang %s', $goodsReceiptNumber),
                                english: sprintf('Goods receipt %s', $goodsReceiptNumber),
                            ),
                        ),
                    );
                    break;
                default:
                    if ($stockLocation->isInternal()) {
                        throw new RuntimeException(
                            sprintf('The stock location reference is currently not implemented in %s.', self::class),
                        );
                    }

                    $configurations->addConfiguration(
                        $stockLocation,
                        new StockLocationConfiguration(
                            stockAvailableForSale: null,
                            code: null,
                            warehouseCode: null,
                            position: null,
                            isInDefaultWarehouse: null,
                            warehouseCreationDate: null,
                            globalUniqueDisplayName: new Translation(
                                german: 'Externe Lagerort',
                                english: 'External stock location',
                            ),
                        ),
                    );
                    break;
            }
        }

        return $configurations;
    }
}
