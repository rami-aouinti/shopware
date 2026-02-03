<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DatabaseBulkInsertService;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\PhpStandardLibrary\Collection\Map;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\PaperTrail\ErpPaperTrailUri;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailUriProvider;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StockNotAvailableForSaleUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly DatabaseBulkInsertService $bulkInsertWithUpdate,
        private readonly PaperTrailUriProvider $paperTrailUriProvider,
        private readonly PaperTrailLoggingService $paperTrailLoggingService,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            WarehouseDefinition::ENTITY_WRITTEN_EVENT => 'warehouseWritten',
            GoodsReceiptDefinition::ENTITY_WRITTEN_EVENT => 'goodsReceiptWritten',
            StockContainerDefinition::ENTITY_WRITTEN_EVENT => 'stockContainerWritten',
            EntityPreWriteValidationEventDispatcher::getEventName(WarehouseDefinition::ENTITY_NAME) => 'triggerChangeSetForWarehouseChanges',
            EntityPreWriteValidationEventDispatcher::getEventName(GoodsReceiptDefinition::ENTITY_NAME) => 'triggerChangeSetForGoodsReceiptChanges',
            EntityPreWriteValidationEventDispatcher::getEventName(StockContainerDefinition::ENTITY_NAME) => 'triggerChangeSetForStockContainerChanges',
        ];
    }

    public function triggerChangeSetForGoodsReceiptChanges(EntityPreWriteValidationEvent $event): void
    {
        self::triggerChangeSetForStockLocationChanges($event, GoodsReceiptDefinition::ENTITY_NAME);
    }

    public static function triggerChangeSetForStockContainerChanges(EntityPreWriteValidationEvent $event): void
    {
        self::triggerChangeSetForStockLocationChanges($event, StockContainerDefinition::ENTITY_NAME);
    }

    public static function triggerChangeSetForStockLocationChanges(
        EntityPreWriteValidationEvent $event,
        string $stockLocationEntityName,
    ): void {
        if (!($event instanceof EntityPreWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        foreach ($event->getCommands() as $command) {
            if (
                !($command instanceof UpdateCommand)
                || $command->getEntityName() !== $stockLocationEntityName
            ) {
                continue;
            }
            if ($command->hasField('warehouse_id')) {
                $command->requestChangeSet();
            }
        }
    }

    public function triggerChangeSetForWarehouseChanges($event): void
    {
        if (!($event instanceof EntityPreWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        foreach ($event->getCommands() as $command) {
            if (
                !($command instanceof UpdateCommand)
                || $command->getEntityName() !== WarehouseDefinition::ENTITY_NAME
            ) {
                continue;
            }
            if ($command->hasField('is_stock_available_for_sale')) {
                $command->requestChangeSet();
            }
        }
    }

    public function warehouseWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('warehouse-update'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Stock not available for sale update triggered',
            [
                'trigger' => 'warehouse-update',
                'warehouseIds' => $entityWrittenEvent->getIds(),
            ],
        );

        $warehouseIdsToDecreaseStockNotAvailableForSale = [];
        $warehouseIdsToIncreaseStockNotAvailableForSale = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            // Should be not null when 'isStockAvailableForSale' has changed as we requested a change set in
            // triggerChangeSetForWarehouseChanges.
            $changeSet = $writeResult->getChangeSet();
            if (
                ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE)
                || !array_key_exists('isStockAvailableForSale', $payload)
                || !$changeSet
                || !$changeSet->hasChanged('is_stock_available_for_sale')
            ) {
                continue;
            }

            if ($payload['isStockAvailableForSale']) {
                // Warehouse stock is now available for sale, decrease stockNotAvailableForSale
                $warehouseIdsToDecreaseStockNotAvailableForSale[] = $writeResult->getPrimaryKey();
            } else {
                // Warehouse stock is no longer available for sale, increase stockNotAvailableForSale
                $warehouseIdsToIncreaseStockNotAvailableForSale[] = $writeResult->getPrimaryKey();
            }
        }

        $context = $entityWrittenEvent->getContext();
        if (count($warehouseIdsToDecreaseStockNotAvailableForSale) > 0) {
            $this->updateProductStockNotAvailableForSaleByWarehouseStock(-1, $warehouseIdsToDecreaseStockNotAvailableForSale);
            $this->paperTrailLoggingService->logPaperTrailEvent(
                'Decreased stockNotAvailableForSale for products in warehouses that are now available for sale',
                [
                    'warehouseIds' => $warehouseIdsToDecreaseStockNotAvailableForSale,
                ],
            );
            $this->eventDispatcher->dispatch(new StockNotAvailableForSaleUpdatedForAllProductsInWarehousesEvent(
                $warehouseIdsToDecreaseStockNotAvailableForSale,
                false,
                $context,
            ));
        }
        if (count($warehouseIdsToIncreaseStockNotAvailableForSale) > 0) {
            $this->updateProductStockNotAvailableForSaleByWarehouseStock(1, $warehouseIdsToIncreaseStockNotAvailableForSale);
            $this->paperTrailLoggingService->logPaperTrailEvent(
                'Increased stockNotAvailableForSale for products in warehouses that are no longer available for sale',
                [
                    'warehouseIds' => $warehouseIdsToIncreaseStockNotAvailableForSale,
                ],
            );
            $this->eventDispatcher->dispatch(new StockNotAvailableForSaleUpdatedForAllProductsInWarehousesEvent(
                $warehouseIdsToIncreaseStockNotAvailableForSale,
                true,
                $context,
            ));
        }
        $this->paperTrailUriProvider->reset();
    }

    public function goodsReceiptWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        $warehouseChanges = $this->getWarehouseChangesByStockLocationPrimaryKey($entityWrittenEvent);
        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('goods-receipt-update'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Stock not available for sale update triggered',
            [
                'trigger' => 'goods-receipt-update',
                'goodsReceiptIds' => $entityWrittenEvent->getIds(),
                'warehouseChanges' => array_map(
                    fn(array $warehouseChange) => [
                        'oldWarehouseId' => doIf($warehouseChange['oldWarehouseId'], Uuid::fromBytesToHex(...)),
                        'newWarehouseId' => $warehouseChange['newWarehouseId'] ?? null,
                    ],
                    $warehouseChanges,
                ),
            ],
        );

        $this->updateProductStockNotAvailableForSaleByGoodsReceiptWarehouseChanges(
            warehouseChangesByPrimaryKey: $warehouseChanges,
            stockIdentifier: '`stock`.`goods_receipt_id`',
        );
        $this->paperTrailUriProvider->reset();
    }

    public function stockContainerWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        $warehouseChanges = $this->getWarehouseChangesByStockLocationPrimaryKey($entityWrittenEvent);
        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('stock-container-update'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Stock not available for sale update triggered',
            [
                'trigger' => 'stock-container-update',
                'stockContainerIds' => $entityWrittenEvent->getIds(),
                'warehouseChanges' => array_map(
                    fn(array $warehouseChange) => [
                        'oldWarehouseId' => doIf($warehouseChange['oldWarehouseId'], Uuid::fromBytesToHex(...)),
                        'newWarehouseId' => $warehouseChange['newWarehouseId'] ?? null,
                    ],
                    $warehouseChanges,
                ),
            ],
        );

        $this->updateProductStockNotAvailableForSaleByGoodsReceiptWarehouseChanges(
            warehouseChangesByPrimaryKey: $warehouseChanges,
            stockIdentifier: '`stock`.`stock_container_id`',
        );
        $this->paperTrailUriProvider->reset();
    }

    private function getWarehouseChangesByStockLocationPrimaryKey(EntityWrittenEvent $entityWrittenEvent): array
    {
        $warehouseChangesByGoodsReceiptIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            $changeSet = $writeResult->getChangeSet();
            if (
                ($writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE)
                && $changeSet
                && $changeSet->hasChanged('warehouse_id')
                && array_key_exists('warehouseId', $payload)
            ) {
                $warehouseChangesByGoodsReceiptIds[$writeResult->getPrimaryKey()] = [
                    'oldWarehouseId' => $changeSet->getBefore('warehouse_id'),
                    'newWarehouseId' => $payload['warehouseId'],
                ];
            }
        }

        return $warehouseChangesByGoodsReceiptIds;
    }

    /**
     * Updates the not available for sale stocks incrementally by iterating the given stock movements.
     *
     * DEPENDS ON stock_not_available_for_sale being correctly calculated for all other stock movements for the same
     * products besides the given ones.
     *
     * DEPENDS ON pickware products being initialized
     */
    public function updateProductStockNotAvailableForSaleByStockMovements(array $stockMovementIds, Context $context): void
    {
        $stockMovementIds = array_values(array_unique($stockMovementIds));
        $stockMovements = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(product_id)) AS productId,
                quantity,
                COALESCE(
                    sourceWarehouse.id,
                    sourceBinLocationWarehouse.id,
                    sourceGoodsReceiptWarehouse.id,
                    sourceStockContainerWarehouse.id
                ) AS sourceWarehouseId,
                COALESCE(
                    sourceWarehouse.is_stock_available_for_sale,
                    sourceBinLocationWarehouse.is_stock_available_for_sale,
                    sourceGoodsReceiptWarehouse.is_stock_available_for_sale,
                    sourceStockContainerWarehouse.is_stock_available_for_sale
                ) AS sourceWarehouseIsStockAvailableForSale,
                COALESCE(
                    destinationWarehouse.id,
                    destinationBinLocationWarehouse.id,
                    destinationGoodsReceiptWarehouse.id,
                    destinationStockContainerWarehouse.id
                ) AS destinationWarehouseId,
                COALESCE(
                    destinationWarehouse.is_stock_available_for_sale,
                    destinationBinLocationWarehouse.is_stock_available_for_sale,
                    destinationGoodsReceiptWarehouse.is_stock_available_for_sale,
                    destinationStockContainerWarehouse.is_stock_available_for_sale
                ) AS destinationWarehouseIsStockAvailableForSale

            FROM pickware_erp_stock_movement stockMovement
            LEFT JOIN pickware_erp_warehouse sourceWarehouse ON sourceWarehouse.id = stockMovement.source_warehouse_id
            LEFT JOIN pickware_erp_bin_location sourceBinLocation ON sourceBinLocation.id = stockMovement.source_bin_location_id
                LEFT JOIN pickware_erp_warehouse sourceBinLocationWarehouse ON sourceBinLocationWarehouse.id = sourceBinLocation.warehouse_id
            LEFT JOIN pickware_erp_warehouse destinationWarehouse ON destinationWarehouse.id = stockMovement.destination_warehouse_id
            LEFT JOIN pickware_erp_bin_location destinationBinLocation ON destinationBinLocation.id = stockMovement.destination_bin_location_id
                LEFT JOIN pickware_erp_warehouse destinationBinLocationWarehouse ON destinationBinLocationWarehouse.id = destinationBinLocation.warehouse_id
            LEFT JOIN pickware_erp_goods_receipt sourceGoodsReceipt ON sourceGoodsReceipt.id = stockMovement.source_goods_receipt_id
                LEFT JOIN pickware_erp_warehouse sourceGoodsReceiptWarehouse ON sourceGoodsReceiptWarehouse.id = sourceGoodsReceipt.warehouse_id
            LEFT JOIN pickware_erp_goods_receipt destinationGoodsReceipt ON destinationGoodsReceipt.id = stockMovement.destination_goods_receipt_id
                LEFT JOIN pickware_erp_warehouse destinationGoodsReceiptWarehouse ON destinationGoodsReceiptWarehouse.id = destinationGoodsReceipt.warehouse_id
            LEFT JOIN pickware_erp_stock_container sourceStockContainer ON sourceStockContainer.id = stockMovement.source_stock_container_id
                LEFT JOIN pickware_erp_warehouse sourceStockContainerWarehouse ON sourceStockContainerWarehouse.id = sourceStockContainer.warehouse_id
            LEFT JOIN pickware_erp_stock_container destinationStockContainer ON destinationStockContainer.id = stockMovement.destination_stock_container_id
                LEFT JOIN pickware_erp_warehouse destinationStockContainerWarehouse ON destinationStockContainerWarehouse.id = destinationStockContainer.warehouse_id

            WHERE stockMovement.id IN (:stockMovementIds)
              AND product_version_id = :liveVersionId
              AND (
                  # Note that "<>" comparator does not work with NULL values. Hence, the verbose check.
                  COALESCE(
                      sourceWarehouse.id,
                      sourceBinLocationWarehouse.id,
                      sourceGoodsReceiptWarehouse.id,
                      sourceStockContainerWarehouse.id
                      ) IS NULL &&
                  COALESCE(
                      destinationWarehouse.id,
                      destinationBinLocationWarehouse.id,
                      destinationGoodsReceiptWarehouse.id,
                      destinationStockContainerWarehouse.id
                      ) IS NOT NULL ||
                  COALESCE(
                      sourceWarehouse.id,
                      sourceBinLocationWarehouse.id,
                      sourceGoodsReceiptWarehouse.id,
                      sourceStockContainerWarehouse.id
                      ) IS NOT NULL &&
                  COALESCE(
                      destinationWarehouse.id,
                      destinationBinLocationWarehouse.id,
                      destinationGoodsReceiptWarehouse.id,
                      destinationStockContainerWarehouse.id
                      ) IS NULL ||
                  COALESCE(
                      sourceWarehouse.id,
                      sourceBinLocationWarehouse.id,
                      sourceGoodsReceiptWarehouse.id,
                      sourceStockContainerWarehouse.id
                      ) <>
                  COALESCE(
                      destinationWarehouse.id,
                      destinationBinLocationWarehouse.id,
                      destinationGoodsReceiptWarehouse.id,
                      destinationStockContainerWarehouse.id
                      )
              )',
            [
                'stockMovementIds' => array_map('hex2bin', $stockMovementIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'stockMovementIds' => ArrayParameterType::STRING,
            ],
        );

        $stockNotAvailableForSaleChanges = [];
        foreach ($stockMovements as $stockMovement) {
            $sourceIsWarehouse = (bool) $stockMovement['sourceWarehouseId'];
            $sourceWarehouseIsStockAvailableForSale = (bool) $stockMovement['sourceWarehouseIsStockAvailableForSale'];
            $destinationIsWarehouse = (bool) $stockMovement['destinationWarehouseId'];
            $destinationWarehouseIsStockAvailableForSale = (bool) $stockMovement['destinationWarehouseIsStockAvailableForSale'];

            if ($sourceIsWarehouse && !$sourceWarehouseIsStockAvailableForSale && ($destinationWarehouseIsStockAvailableForSale || !$destinationIsWarehouse)) {
                $stockNotAvailableForSaleChanges[] = [
                    'productId' => $stockMovement['productId'],
                    'change' => -1 * (int) $stockMovement['quantity'],
                ];
            }
            if ($destinationIsWarehouse && !$destinationWarehouseIsStockAvailableForSale && ($sourceWarehouseIsStockAvailableForSale || !$sourceIsWarehouse)) {
                $stockNotAvailableForSaleChanges[] = [
                    'productId' => $stockMovement['productId'],
                    'change' => (int) $stockMovement['quantity'],
                ];
            }
        }

        if (count($stockNotAvailableForSaleChanges) > 0) {
            $productIds = array_values(array_unique(array_column($stockNotAvailableForSaleChanges, 'productId')));
            foreach ($stockNotAvailableForSaleChanges as $stockAvailableForSaleChange) {
                $this->persistStockNotAvailableForSaleChange(
                    $stockAvailableForSaleChange['productId'],
                    $stockAvailableForSaleChange['change'],
                );
            }

            $this->paperTrailLoggingService->logPaperTrailEvent(
                'Stock not available for sale updated',
                [
                    'calculation' => 'stock-movements',
                    'changes' => $stockNotAvailableForSaleChanges,
                ],
            );
            $this->eventDispatcher->dispatch(new StockNotAvailableForSaleUpdatedEvent($productIds, $context));
        }
    }

    private function persistStockNotAvailableForSaleChange(string $productId, int $change): void
    {
        $this->db->executeStatement(
            'UPDATE `pickware_erp_pickware_product`
            SET `pickware_erp_pickware_product`.`stock_not_available_for_sale` = `pickware_erp_pickware_product`.`stock_not_available_for_sale` + (:change)
            WHERE `pickware_erp_pickware_product`.`product_id` = :productId
            AND `pickware_erp_pickware_product`.`product_version_id` = :liveVersionId;',
            [
                'productId' => hex2bin($productId),
                'change' => $change,
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        );
    }

    private function updateProductStockNotAvailableForSaleByGoodsReceiptWarehouseChanges(
        array $warehouseChangesByPrimaryKey,
        string $stockIdentifier,
    ): void {
        if (count($warehouseChangesByPrimaryKey) === 0) {
            return;
        }

        foreach ($warehouseChangesByPrimaryKey as $primaryKey => $payload) {
            $this->db->executeStatement(
                'UPDATE `pickware_erp_pickware_product` pickwareProduct

                INNER JOIN `pickware_erp_stock` stock
                ON stock.`product_id` = pickwareProduct.`product_id`
                AND stock.`product_version_id` = pickwareProduct.`product_version_id`

                LEFT JOIN `pickware_erp_warehouse` oldWarehouse
                ON oldWarehouse.`id` = :oldWarehouseId

                LEFT JOIN `pickware_erp_warehouse` newWarehouse
                ON newWarehouse.`id` = :newWarehouseId

                SET pickwareProduct.`stock_not_available_for_sale` = pickwareProduct.`stock_not_available_for_sale` + CASE
                    WHEN IFNULL(oldWarehouse.`is_stock_available_for_sale`, 1) = 1 AND IFNULL(newWarehouse.`is_stock_available_for_sale`, 1) = 1
                    THEN 0
                    WHEN IFNULL(oldWarehouse.`is_stock_available_for_sale`, 1) = 0 AND IFNULL(newWarehouse.`is_stock_available_for_sale`, 1) = 1
                    THEN -1 * stock.`quantity`
                    WHEN IFNULL(oldWarehouse.`is_stock_available_for_sale`, 1) = 1 AND IFNULL(newWarehouse.`is_stock_available_for_sale`, 1) = 0
                    THEN stock.`quantity`
                    ELSE 0
                END
                WHERE ' . $stockIdentifier . ' = :primaryKey
                AND pickwareProduct.`product_version_id` = :liveVersionId;',
                [
                    'primaryKey' => hex2bin($primaryKey),
                    'stockIdentifier' => $stockIdentifier,
                    'oldWarehouseId' => $payload['oldWarehouseId'],
                    'newWarehouseId' => $payload['newWarehouseId'] ? hex2bin($payload['newWarehouseId']) : null,
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
            );
        }
    }

    /**
     * When a warehouse "isStockAvailableForSale" flag is changed, we need to update the stock_not_available_for_sale of
     * _ALL AFFECTED PRODUCTS_. Two kinds of stock do not need to be recalculated - they can simply be added/subtracted
     * from the stock_not_available_for_sale for all affected products. These stocks are:
     *  - the warehouse stock of the warehouse, that change its "isStockAvailableForSale" flag
     *  - goods receipts that are assigned to the warehouse, that change its "isStockAvailableForSale" flag
     *
     * @param int $stockNotAvailableForSaleFactor 1 or -1 whether or not the online not available stock should be
     * increased (1) or decreased (-1)
     */
    private function updateProductStockNotAvailableForSaleByWarehouseStock(
        int $stockNotAvailableForSaleFactor,
        array $warehouseIds,
    ): void {
        $this->db->executeStatement(
            'UPDATE `pickware_erp_pickware_product` pickwareProduct

            LEFT JOIN `pickware_erp_warehouse_stock` warehouseStock
            ON warehouseStock.`product_id` = pickwareProduct.`product_id`
            AND warehouseStock.`product_version_id` = pickwareProduct.`product_version_id`
            AND warehouseStock.`warehouse_id` IN (:warehouseIds)
            AND warehouseStock.`quantity` > 0

            LEFT JOIN (
                SELECT
                    goodsReceipt.`warehouse_id` AS warehouseId,
                    stock.`product_id` AS productId,
                    stock.`product_version_id` AS productVersionId,
                    SUM(stock.`quantity`) AS quantity
                FROM `pickware_erp_warehouse` warehouse

                LEFT JOIN `pickware_erp_goods_receipt` goodsReceipt
                ON goodsReceipt.`warehouse_id` = warehouse.`id`

                LEFT JOIN `pickware_erp_stock_container` stockContainer
                ON stockContainer.`warehouse_id` = warehouse.`id`

                INNER JOIN `pickware_erp_stock` stock
                ON stock.`goods_receipt_id` = goodsReceipt.`id` OR stock.`stock_container_id` = stockContainer.`id`
                AND stock.`quantity` > 0

                WHERE warehouse.`id` IN (:warehouseIds)
                GROUP BY productId
            ) AS goodsReceiptAndStockConatinerStock
            ON pickwareProduct.`product_id` = goodsReceiptAndStockConatinerStock.productId
            AND pickwareProduct.`product_version_id` = goodsReceiptAndStockConatinerStock.productVersionId

            SET pickwareProduct.`stock_not_available_for_sale` = pickwareProduct.`stock_not_available_for_sale`
              + (' . $stockNotAvailableForSaleFactor . ' * (IFNULL(warehouseStock.`quantity`, 0) + IFNULL(goodsReceiptAndStockConatinerStock.`quantity`, 0)))

            WHERE pickwareProduct.`product_version_id` = :liveVersionId
            AND (
                (goodsReceiptAndStockConatinerStock.`quantity` IS NOT NULL && warehouseStock.`quantity` IS NOT NULL) ||
                warehouseStock.`quantity` IS NOT NULL ||
                goodsReceiptAndStockConatinerStock.`quantity` IS NOT NULL
            );',
            [
                'warehouseIds' => array_map('hex2bin', $warehouseIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'warehouseIds' => ArrayParameterType::STRING,
            ],
        );
    }

    /**
     * This is the indexer scenario when the stock not available for sale needs to be recalculated from the ground up.
     * This can be done in a single query since the given number of product ids and number of warehouses is manageable.
     *
     * DEPENDS ON pickware_erp_warehouse_stock and pickware_erp_stock to have been calculated correctly before for the
     * given products.
     *
     * @param String[] $productIds
     */
    public function calculateStockNotAvailableForSaleForProducts(array $productIds, Context $context): void
    {
        if (count($productIds) === 0) {
            return;
        }

        $pickwareProductRows = $this->db->fetchAllAssociative(
            'SELECT
                pickwareProduct.`id`,
                LOWER(HEX(pickwareProduct.`product_id`)) AS productId,
                pickwareProduct.`product_version_id`,
                IFNULL(warehouseStockAggregation.quantity, 0) + IFNULL(stockAggregation.quantity, 0) AS stockNotAvailableForSale
            FROM `pickware_erp_pickware_product` pickwareProduct
            INNER JOIN (
                SELECT
                    warehouseStock.`product_id` AS product_id,
                    warehouseStock.`product_version_id` AS product_version_id,
                    SUM(
                        IF(
                            `is_stock_available_for_sale` = 1,
                            0,
                            warehouseStock.quantity
                        )
                    ) AS quantity

                FROM `pickware_erp_warehouse_stock` warehouseStock
                INNER JOIN `pickware_erp_warehouse` warehouse ON warehouseStock.`warehouse_id` = warehouse.`id`

                WHERE warehouseStock.`product_id` IN (:productIds)
                AND warehouseStock.`product_version_id` = :liveVersionId
                GROUP BY warehouseStock.`product_id`
            ) AS warehouseStockAggregation
            ON pickwareProduct.`product_id` = warehouseStockAggregation.`product_id`
            AND pickwareProduct.`product_version_id` = warehouseStockAggregation.`product_version_id`

            LEFT JOIN (
                SELECT
                    stock.`product_id` AS product_id,
                    stock.`product_version_id` AS product_version_id,
                    SUM(
                        IF(
                            warehouse.`is_stock_available_for_sale` = 1,
                            0,
                            stock.`quantity`
                        )
                    ) AS quantity

                FROM `pickware_erp_stock` stock
                LEFT JOIN `pickware_erp_goods_receipt` goodsReceipt ON goodsReceipt.`id` = stock.`goods_receipt_id`
                LEFT JOIN `pickware_erp_stock_container` stockContainer ON stockContainer.`id` = stock.`stock_container_id`
                INNER JOIN `pickware_erp_warehouse` warehouse ON warehouse.`id` = goodsReceipt.`warehouse_id` OR warehouse.`id` = stockContainer.`warehouse_id`

                WHERE stock.`product_id` IN (:productIds)
                AND stock.`product_version_id` = :liveVersionId
                GROUP BY stock.`product_id`
            ) as stockAggregation
            ON pickwareProduct.`product_id` = stockAggregation.`product_id`
            AND pickwareProduct.`product_version_id` = stockAggregation.`product_version_id`

            WHERE pickwareProduct.`product_id` IN (:productIds)
            AND pickwareProduct.`product_version_id` = :liveVersionId',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
            ],
        );

        /** @var Map<string, int> $stockNotAvailableForSaleByProductId */
        $stockNotAvailableForSaleByProductId = new Map();
        foreach ($pickwareProductRows as $row) {
            $stockNotAvailableForSaleByProductId->set($row['productId'], (int) $row['stockNotAvailableForSale']);
        }

        $this->eventDispatcher->dispatch(new ProductStockNotAvailableForSaleCalculationEvent(
            $productIds,
            $stockNotAvailableForSaleByProductId,
            $context,
        ));

        $pickwareProductStockNotAvailableForSale = [];
        foreach ($pickwareProductRows as $row) {
            $pickwareProductStockNotAvailableForSale[] = [
                'id' => $row['id'],
                'product_id' => Uuid::fromHexToBytes($row['productId']),
                'product_version_id' => $row['product_version_id'],
                'stock_not_available_for_sale' => $stockNotAvailableForSaleByProductId->get($row['productId']),
                'updated_at' => (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'created_at' => (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $this->bulkInsertWithUpdate->insertOnDuplicateKeyUpdate(
            'pickware_erp_pickware_product',
            $pickwareProductStockNotAvailableForSale,
            [],
            ['stock_not_available_for_sale'],
        );

        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Stock not available for sale updated',
            [
                'calculation' => 'products',
                'changes' => array_map(
                    fn(array $row) => [
                        'productId' => $row['productId'],
                        'change' => $stockNotAvailableForSaleByProductId->get($row['productId']),
                    ],
                    $pickwareProductRows,
                ),
            ],
        );
        $this->eventDispatcher->dispatch(new StockNotAvailableForSaleUpdatedEvent($productIds, $context));
    }
}
