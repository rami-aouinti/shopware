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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class WarehouseStockUpdater
{
    public function __construct(
        private readonly Connection $db,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly WarehouseStockInitializer $warehouseStockInitializer,
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Updates the warehouse stocks incrementally by iterating the given stock movements.
     *
     * DEPENDS ON `pickware_erp_warehouse_stock` being correctly calculated for all other stock movements for the same
     * products and warehouse stocks besides the given ones.
     */
    public function indexStockMovements(array $stockMovementIds, Context $context): void
    {
        $stockMovementIds = array_values(array_unique($stockMovementIds));
        $stockMovements = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(product_id)) AS productId,
                LOWER(HEX(product_version_id)) AS productVersionId,
                quantity,
                LOWER(HEX(COALESCE(
                    source_warehouse_id,
                    sourceBinLocation.warehouse_id
                ))) AS sourceWarehouseId,
                LOWER(HEX(COALESCE(
                    destination_warehouse_id,
                    destinationBinLocation.warehouse_id
                ))) AS destinationWarehouseId
            FROM pickware_erp_stock_movement stockMovement
            LEFT JOIN pickware_erp_bin_location sourceBinLocation ON sourceBinLocation.id = stockMovement.source_bin_location_id
            LEFT JOIN pickware_erp_bin_location destinationBinLocation ON destinationBinLocation.id = stockMovement.destination_bin_location_id
            WHERE stockMovement.id IN (:stockMovementIds) AND product_version_id = :liveVersionId',
            [
                'stockMovementIds' => array_map('hex2bin', $stockMovementIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'stockMovementIds' => ArrayParameterType::STRING,
            ],
        );

        // Update warehouse stocks if stock was moved to or from a warehouse or bin location (in that warehouse) or
        // stock container (in that warehouse).
        $warehouseIds = [];
        $productIds = [];
        foreach ($stockMovements as $stockMovement) {
            if ($stockMovement['sourceWarehouseId'] === $stockMovement['destinationWarehouseId']) {
                // If the source and destination warehouse is identical (e.g. a stock move from one location in the
                // warehouse to another location in that warehouse), we do not need to track that warehouse id for the
                // warehouse stock change event. Because the stock in that warehouse did not change (stock ∓0).
                continue;
            }

            $productIds = array_unique(array_merge(
                $productIds,
                [$stockMovement['productId']],
            ));
            if ($stockMovement['sourceWarehouseId']) {
                $this->persistWarehouseStockChange([
                    'productId' => $stockMovement['productId'],
                    'productVersionId' => $stockMovement['productVersionId'],
                    'warehouseId' => $stockMovement['sourceWarehouseId'],
                    'changeAmount' => -1 * $stockMovement['quantity'],
                ]);
                $warehouseIds = array_unique(array_merge(
                    $warehouseIds,
                    [$stockMovement['sourceWarehouseId']],
                ));
            }
            if ($stockMovement['destinationWarehouseId']) {
                $this->persistWarehouseStockChange([
                    'productId' => $stockMovement['productId'],
                    'productVersionId' => $stockMovement['productVersionId'],
                    'warehouseId' => $stockMovement['destinationWarehouseId'],
                    'changeAmount' => 1 * $stockMovement['quantity'],
                ]);
                $warehouseIds = array_unique(array_merge(
                    $warehouseIds,
                    [$stockMovement['destinationWarehouseId']],
                ));
            }
        }

        if (count($warehouseIds) > 0) {
            $this->eventDispatcher->dispatch(
                new WarehouseStockUpdatedEvent($warehouseIds, $productIds, $context),
                WarehouseStockUpdatedEvent::EVENT_NAME,
            );
        }
    }

    private function persistWarehouseStockChange(array $payload): void
    {
        $this->db->executeStatement(
            'INSERT INTO pickware_erp_warehouse_stock (
                id,
                product_id,
                product_version_id,
                quantity,
                warehouse_id,
                created_at
            ) VALUES (
                :id,
                :productId,
                :productVersionId,
                :changeAmount,
                :warehouseId,
                UTC_TIMESTAMP(3)
            ) ON DUPLICATE KEY UPDATE
                quantity = quantity + VALUES(quantity),
                updated_at = UTC_TIMESTAMP(3)',
            [
                'id' => Uuid::randomBytes(),
                'productId' => hex2bin($payload['productId']),
                'productVersionId' => hex2bin($payload['productVersionId']),
                'warehouseId' => hex2bin($payload['warehouseId']),
                'changeAmount' => $payload['changeAmount'],
            ],
        );
    }

    /**
     * This is the indexer scenario. Updates all warehouse stocks for the given products.
     *
     * DEPENDS ON pickware_erp_stock to have been calculated before for the given products.
     *
     * The warehouse stocks are summed up from all warehouse-stock-relevant `pickware_erp_stock`s. The reference to the
     * respective warehouse needs to be manually selected (not automatically). Therefore, when the list of
     * warehouse-stock-relevant stock locations changes, we also need to update this query!
     *
     * @param String[] $productIds
     */
    public function calculateWarehouseStockForProducts(array $productIds, Context $context): void
    {
        RetryableTransaction::retryable($this->db, function() use ($productIds): void {
            $this->warehouseStockInitializer->ensureProductWarehouseStockForProductsExist($productIds);
        });
        RetryableTransaction::retryable($this->db, function() use ($productIds): void {
            $this->db->executeStatement(
                'UPDATE `pickware_erp_warehouse_stock`
                 LEFT JOIN (
                     SELECT
                        stock.`product_id` as productId,
                        stock.`product_version_id` as productVersionId,
                        SUM(stock.`quantity`) as quantity,
                        COALESCE(
                            stock.`warehouse_id`, # stock location "warehouse"
                            binLocation.`warehouse_id` # stock location "bin_location"
                        ) as warehouseId

                    FROM `pickware_erp_stock` stock
                    LEFT JOIN `pickware_erp_bin_location` binLocation
                    ON stock.`bin_location_id` = binLocation.`id`

                    WHERE stock.`product_id` IN (:productIds)
                    AND stock.`product_version_id` = :liveVersionId
                    AND stock.`location_type_technical_name` IN (:warehouseStockRelevantStockLocationTypes)

                    GROUP BY COALESCE(
                        stock.`warehouse_id`,
                        binLocation.`warehouse_id`
                    ),
                    stock.`product_id`,
                    stock.`product_version_id`
                ) newWarehouseStocks
                ON `pickware_erp_warehouse_stock`.`product_id` = newWarehouseStocks.productId
                AND `pickware_erp_warehouse_stock`.`product_version_id` = newWarehouseStocks.productVersionId
                AND `pickware_erp_warehouse_stock`.`warehouse_id` = newWarehouseStocks.warehouseId

                SET `pickware_erp_warehouse_stock`.quantity = COALESCE(newWarehouseStocks.`quantity`, 0)

                WHERE `pickware_erp_warehouse_stock`.`product_id` IN (:productIds)',
                [
                    'productIds' => array_map('hex2bin', $productIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'warehouseStockRelevantStockLocationTypes' => [
                        LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                        LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                    ],
                ],
                [
                    'productIds' => ArrayParameterType::STRING,
                    'warehouseStockRelevantStockLocationTypes' => ArrayParameterType::STRING,
                ],
            );
        });

        $allWarehouseIds = $this->entityManager->findAllIds(
            WarehouseDefinition::class,
            $context,
        );
        $this->eventDispatcher->dispatch(
            new WarehouseStockUpdatedEvent(
                warehouseIds: $allWarehouseIds,
                productIds: $productIds,
                context: $context,
            ),
            WarehouseStockUpdatedEvent::EVENT_NAME,
        );
    }
}
