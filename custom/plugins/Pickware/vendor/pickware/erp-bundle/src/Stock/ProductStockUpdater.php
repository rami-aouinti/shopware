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
use Doctrine\DBAL\Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\Batch\BatchManagementDevFeatureFlag;
use Pickware\PickwareErpStarter\Batch\BatchStockMappingService;
use Pickware\PickwareErpStarter\Batch\BatchStockMappingServiceValidationException;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductStockUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly BatchStockMappingService $batchStockMappingService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly ProductStockLocationMappingUpdater $productStockLocationMappingUpdater,
        private readonly ProductStockLocationConfigurationUpdater $productStockLocationConfigurationUpdater,
        private readonly EntityManager $entityManager,
        private readonly PaperTrailLoggingService $paperTrailLoggingService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityPreWriteValidationEventDispatcher::getEventName(ProductWarehouseConfigurationDefinition::ENTITY_NAME) => 'preWriteValidation',
            ProductWarehouseConfigurationDefinition::ENTITY_WRITTEN_EVENT => 'productWarehouseConfigurationWritten',
        ];
    }

    public function preWriteValidation($event): void
    {
        if (!($event instanceof EntityPreWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        foreach ($event->getCommands() as $command) {
            if ($command instanceof ChangeSetAware) {
                $command->requestChangeSet();
            }
        }
    }

    /**
     * This is the indexer scenario. Updates product stocks for the given products.
     *
     * DEPENDS ON no other calculation beforehand.
     * @param string[] $productIds
     */
    public function recalculateStockFromStockMovementsForProducts(array $productIds, Context $context): void
    {
        RetryableTransaction::retryable($this->db, function() use ($productIds, $context): void {
            // Zero out quantities instead of deleting to preserve batch mappings
            $this->db->executeStatement(
                'UPDATE `pickware_erp_stock`
                SET `quantity` = 0, `updated_at` = UTC_TIMESTAMP(3)
                WHERE `product_id` IN (:productIds)',
                [
                    'productIds' => array_map('hex2bin', $productIds),
                ],
                [
                    'productIds' => ArrayParameterType::STRING,
                ],
            );

            $this->upsertStockFromStockMovements($productIds, $context);

            $this->cleanUpStocks($productIds, $context);
        });

        $this->recalculateProductPhysicalStock($productIds, $context);
    }

    /**
     * @param string[] $productIds
     */
    private function upsertStockFromStockMovements(array $productIds, Context $context): void
    {
        // This query extracts the common parts of the UPDATE and INSERT queries
        $stockMovementCalculationSql = '
            SELECT
                `product_id`,
                `product_version_id`,
                SUM(`quantity`) AS `calculated_quantity`,
                `location_type_technical_name`,
                `warehouse_id`,
                `bin_location_id`,
                `order_id`,
                `stock_container_id`,
                `goods_receipt_id`,
                `return_order_id`,
                `special_stock_location_technical_name`
            FROM (
                SELECT
                    `product_id`,
                    `product_version_id`,
                    -1 * `quantity` AS quantity,
                    `source_location_type_technical_name` AS location_type_technical_name,
                    `source_special_stock_location_technical_name` AS special_stock_location_technical_name,
                    `source_warehouse_id` AS warehouse_id,
                    `source_bin_location_id` AS bin_location_id,
                    `source_order_id` AS order_id,
                    `source_stock_container_id` AS stock_container_id,
                    `source_goods_receipt_id` AS goods_receipt_id,
                    `source_return_order_id` AS return_order_id
                FROM
                    `pickware_erp_stock_movement`
                WHERE
                    product_id IN (:productIds)
                    AND product_version_id = :liveVersionId
                UNION ALL -- It is very important to use UNION ALL because UNION selects only distinct values by default
                SELECT
                    `product_id`,
                    `product_version_id`,
                    `quantity` AS quantity,
                    `destination_location_type_technical_name` AS location_type_technical_name,
                    `destination_special_stock_location_technical_name` AS special_stock_location_technical_name,
                    `destination_warehouse_id` AS warehouse_id,
                    `destination_bin_location_id` AS bin_location_id,
                    `destination_order_id` AS order_id,
                    `destination_stock_container_id` AS stock_container_id,
                    `destination_goods_receipt_id` AS goods_receipt_id,
                    `destination_return_order_id` AS return_order_id
                FROM
                    `pickware_erp_stock_movement`
                WHERE
                    `product_id` IN (:productIds)
                    AND `product_version_id` = :liveVersionId
            ) AS stock_movements
            GROUP BY
                `product_id`,
                `product_version_id`,
                `location_type_technical_name`,
                `warehouse_id`,
                `bin_location_id`,
                `order_id`,
                `stock_container_id`,
                `goods_receipt_id`,
                `return_order_id`,
                `special_stock_location_technical_name`
            HAVING
                `calculated_quantity` != 0';

        $parameters = [
            'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            'productIds' => array_map('hex2bin', $productIds),
        ];
        $parameterTypes = [
            'productIds' => ArrayParameterType::STRING,
        ];

        // Step 1: Update existing stock entities with stock calculated from the stock movements
        $this->db->executeStatement(
            'UPDATE `pickware_erp_stock` AS `stock`
            INNER JOIN (' . $stockMovementCalculationSql . ') AS `calculated_stocks`
                ON `stock`.`product_id` = `calculated_stocks`.`product_id`
                AND `stock`.`product_version_id` = :liveVersionId
                AND `stock`.`location_type_technical_name` = `calculated_stocks`.`location_type_technical_name`
                AND `stock`.`warehouse_id` <=> `calculated_stocks`.`warehouse_id`
                AND `stock`.`bin_location_id` <=> `calculated_stocks`.`bin_location_id`
                AND `stock`.`order_id` <=> `calculated_stocks`.`order_id`
                AND `stock`.`stock_container_id` <=> `calculated_stocks`.`stock_container_id`
                AND `stock`.`goods_receipt_id` <=> `calculated_stocks`.`goods_receipt_id`
                AND `stock`.`return_order_id` <=> `calculated_stocks`.`return_order_id`
                AND `stock`.`special_stock_location_technical_name` <=> `calculated_stocks`.`special_stock_location_technical_name`
            SET `stock`.`quantity` = `calculated_stocks`.`calculated_quantity`,
                `stock`.`updated_at` = UTC_TIMESTAMP(3)
            WHERE `stock`.`product_id` IN (:productIds)',
            $parameters,
            $parameterTypes,
        );

        // Step 2: Insert only truly new stock locations that don't exist yet
        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_stock` (
                `id`,
                `quantity`,
                `product_id`,
                `product_version_id`,
                `location_type_technical_name`,
                `warehouse_id`,
                `bin_location_id`,
                `order_id`,
                `order_version_id`,
                `stock_container_id`,
                `goods_receipt_id`,
                `return_order_id`,
                `return_order_version_id`,
                `special_stock_location_technical_name`,
                `created_at`,
                `updated_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ' AS id,
                `calculated_quantity` AS quantity,
                `product_id`,
                :liveVersionId,
                `location_type_technical_name`,
                `warehouse_id`,
                `bin_location_id`,
                `order_id`,
                :liveVersionId,
                `stock_container_id`,
                `goods_receipt_id`,
                `return_order_id`,
                :liveVersionId,
                `special_stock_location_technical_name`,
                UTC_TIMESTAMP(3),
                null
            FROM (' . $stockMovementCalculationSql . ') AS `calculated_stocks`
            WHERE NOT EXISTS (
                SELECT 1 FROM `pickware_erp_stock` AS `existing_stock`
                WHERE `existing_stock`.`product_id` = `calculated_stocks`.`product_id`
                AND `existing_stock`.`product_version_id` = :liveVersionId
                AND `existing_stock`.`location_type_technical_name` = `calculated_stocks`.`location_type_technical_name`
                AND `existing_stock`.`warehouse_id` <=> `calculated_stocks`.`warehouse_id`
                AND `existing_stock`.`bin_location_id` <=> `calculated_stocks`.`bin_location_id`
                AND `existing_stock`.`order_id` <=> `calculated_stocks`.`order_id`
                AND `existing_stock`.`stock_container_id` <=> `calculated_stocks`.`stock_container_id`
                AND `existing_stock`.`goods_receipt_id` <=> `calculated_stocks`.`goods_receipt_id`
                AND `existing_stock`.`return_order_id` <=> `calculated_stocks`.`return_order_id`
                AND `existing_stock`.`special_stock_location_technical_name` <=> `calculated_stocks`.`special_stock_location_technical_name`
            )',
            $parameters,
            $parameterTypes,
        );

        $stockIds = $this->entityManager->findIdsBy(
            StockDefinition::class,
            ['productId' => $productIds],
            $context,
        );
        $this->productStockLocationMappingUpdater->ensureProductStockLocationMappingsExistForStockIds($stockIds);
        $this->productStockLocationConfigurationUpdater->recalculateStockBelowReorderPointForStockIds($stockIds);
    }

    /**
     * @param list<StockMovement> $stockMovements
     */
    public function recalculateStockFromStockMovements(array $stockMovements, Context $context): void
    {
        if ($this->featureFlagService->isActive(BatchManagementDevFeatureFlag::NAME)) {
            $this->recalculateStockFromStockMovementsWithBatchManagement($stockMovements, $context);
        } else {
            $stockMovementIds = array_map(fn(StockMovement $stockMovement) => $stockMovement->getId(), $stockMovements);
            $this->recalculateStockFromStockMovementsWithoutBatchManagement($stockMovementIds, $context);
        }
    }

    /**
     * @param list<string> $stockMovementIds
     */
    private function recalculateStockFromStockMovementsWithoutBatchManagement(array $stockMovementIds, Context $context): void
    {
        $stockMovementIds = array_values(array_unique($stockMovementIds));
        $stockMovements = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(product_id)) AS productId,
                LOWER(HEX(product_version_id)) AS productVersionId,
                source_location_type_technical_name AS sourceLocationTypeTechnicalName,
                LOWER(HEX(source_warehouse_id)) AS sourceWarehouseId,
                LOWER(HEX(source_bin_location_id)) AS sourceBinLocationId,
                LOWER(HEX(source_order_id)) AS sourceOrderId,
                LOWER(HEX(source_order_version_id)) AS sourceOrderVersionId,
                LOWER(HEX(source_stock_container_id)) AS sourceStockContainerId,
                LOWER(HEX(source_return_order_id)) AS sourceReturnOrderId,
                LOWER(HEX(source_return_order_version_id)) AS sourceReturnOrderVersionId,
                LOWER(HEX(source_goods_receipt_id)) AS sourceGoodsReceiptId,
                source_special_stock_location_technical_name AS sourceSpecialStockLocationTechnicalName,
                destination_location_type_technical_name AS destinationLocationTypeTechnicalName,
                LOWER(HEX(destination_warehouse_id)) AS destinationWarehouseId,
                LOWER(HEX(destination_bin_location_id)) AS destinationBinLocationId,
                LOWER(HEX(destination_order_id)) AS destinationOrderId,
                LOWER(HEX(destination_order_version_id)) AS destinationOrderVersionId,
                LOWER(HEX(destination_stock_container_id)) AS destinationStockContainerId,
                LOWER(HEX(destination_return_order_id)) AS destinationReturnOrderId,
                LOWER(HEX(destination_return_order_version_id)) AS destinationReturnOrderVersionId,
                LOWER(HEX(destination_goods_receipt_id)) AS destinationGoodsReceiptId,
                destination_special_stock_location_technical_name AS destinationSpecialStockLocationTechnicalName,
                SUM(quantity) AS quantity
            FROM pickware_erp_stock_movement
            WHERE id IN (:stockMovementIds) AND product_version_id = :liveVersionId
            GROUP BY
                `product_id`,
                `source_location_type_technical_name`,
                `source_warehouse_id`,
                `source_bin_location_id`,
                `source_order_id`,
                `source_order_version_id`,
                `source_stock_container_id`,
                `source_return_order_id`,
                `source_return_order_version_id`,
                `source_goods_receipt_id`,
                `source_special_stock_location_technical_name`,
                `destination_location_type_technical_name`,
                `destination_warehouse_id`,
                `destination_bin_location_id`,
                `destination_order_id`,
                `destination_order_version_id`,
                `destination_stock_container_id`,
                `destination_return_order_id`,
                `destination_return_order_version_id`,
                `destination_goods_receipt_id`,
                `destination_special_stock_location_technical_name`',
            [
                'stockMovementIds' => array_map('hex2bin', $stockMovementIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            ['stockMovementIds' => ArrayParameterType::STRING],
        );

        RetryableTransaction::retryable($this->db, function() use ($stockMovements, $context): void {
            $productIds = [];
            foreach ($stockMovements as $stockMovement) {
                $this->persistStockChangePayload(
                    [
                        'productId' => $stockMovement['productId'],
                        'productVersionId' => $stockMovement['productVersionId'],
                        'locationTypeTechnicalName' => $stockMovement['sourceLocationTypeTechnicalName'],
                        'warehouseId' => $stockMovement['sourceWarehouseId'] ?? null,
                        'binLocationId' => $stockMovement['sourceBinLocationId'] ?? null,
                        'orderId' => $stockMovement['sourceOrderId'] ?? null,
                        'orderVersionId' => $stockMovement['sourceOrderVersionId'] ?? null,
                        'stockContainerId' => $stockMovement['sourceStockContainerId'] ?? null,
                        'returnOrderId' => $stockMovement['sourceReturnOrderId'] ?? null,
                        'returnOrderVersionId' => $stockMovement['sourceReturnOrderVersionId'] ?? null,
                        'goodsReceiptId' => $stockMovement['sourceGoodsReceiptId'] ?? null,
                        'specialStockLocationTechnicalName' => $stockMovement['sourceSpecialStockLocationTechnicalName'] ?? null,
                        'changeAmount' => -1 * $stockMovement['quantity'],
                    ],
                );
                $this->persistStockChangePayload(
                    [
                        'productId' => $stockMovement['productId'],
                        'productVersionId' => $stockMovement['productVersionId'],
                        'locationTypeTechnicalName' => $stockMovement['destinationLocationTypeTechnicalName'],
                        'warehouseId' => $stockMovement['destinationWarehouseId'] ?? null,
                        'binLocationId' => $stockMovement['destinationBinLocationId'] ?? null,
                        'orderId' => $stockMovement['destinationOrderId'] ?? null,
                        'orderVersionId' => $stockMovement['destinationOrderVersionId'] ?? null,
                        'stockContainerId' => $stockMovement['destinationStockContainerId'] ?? null,
                        'returnOrderId' => $stockMovement['destinationReturnOrderId'] ?? null,
                        'returnOrderVersionId' => $stockMovement['destinationReturnOrderVersionId'] ?? null,
                        'goodsReceiptId' => $stockMovement['destinationGoodsReceiptId'] ?? null,
                        'specialStockLocationTechnicalName' => $stockMovement['destinationSpecialStockLocationTechnicalName'] ?? null,
                        'changeAmount' => 1 * $stockMovement['quantity'],
                    ],
                );
                $productIds[] = $stockMovement['productId'];
            }
            $productIds = array_unique($productIds);

            $stockIds = $this->entityManager->findIdsBy(
                StockDefinition::class,
                ['productId' => $productIds],
                $context,
            );
            $this->productStockLocationMappingUpdater->ensureProductStockLocationMappingsExistForStockIds($stockIds);
            $this->productStockLocationConfigurationUpdater->recalculateStockBelowReorderPointForStockIds($stockIds);
            $this->cleanUpStocks($productIds, $context);

            $this->recalculateProductPhysicalStock($productIds, $context);
        });

        $this->paperTrailLoggingService->logPaperTrailEvent(
            'stock-recalculated-from-stock-movements',
            [
                'stockMovementIds' => $stockMovementIds,
                'batchManagementEnabled' => false,
            ],
        );
        $this->eventDispatcher->dispatch(new StockUpdatedForStockMovementsEvent($stockMovements, $context));
    }

    /**
     * @param list<StockMovement> $stockMovements
     */
    private function recalculateStockFromStockMovementsWithBatchManagement(array $stockMovements, Context $context): void
    {
        RetryableTransaction::retryable($this->db, function() use ($stockMovements, $context): void {
            [
                $collapsedStockMovements,
                $stockMovementsWithMissingBatchInformation,
            ] = $this->collapseStockMovements($stockMovements, $context);

            // First, process all collapsable stock movements and their total batch changes.
            foreach ($collapsedStockMovements as $stockMovement) {
                $this->persistStockChange($stockMovement->getProductId(), $stockMovement->getSource(), -$stockMovement->getQuantity());
                $this->persistStockChange($stockMovement->getProductId(), $stockMovement->getDestination(), $stockMovement->getQuantity());
            }
            try {
                $this->batchStockMappingService->processBatchStockMappingChangeOfStockMovements(
                    $collapsedStockMovements,
                    $context,
                );
            } catch (BatchStockMappingServiceValidationException $exception) {
                throw ProductStockUpdaterValidationException::fromBatchStockMappingServiceValidationException($exception);
            }

            // Second, process stock movements without batch information and apply batch stock inference in order.
            foreach ($stockMovementsWithMissingBatchInformation as $stockMovement) {
                $this->persistStockChange($stockMovement->getProductId(), $stockMovement->getSource(), -$stockMovement->getQuantity());
                $this->persistStockChange($stockMovement->getProductId(), $stockMovement->getDestination(), $stockMovement->getQuantity());

                $this->batchStockMappingService->processStockMovementWithConservativeBatchStockMappingInference(
                    $stockMovement,
                    $context,
                );
            }

            $productIds = array_unique(array_map(fn(StockMovement $stockMovement) => $stockMovement->getProductId(), $stockMovements));
            $stockIds = $this->entityManager->findIdsBy(
                StockDefinition::class,
                ['productId' => $productIds],
                $context,
            );
            $this->productStockLocationMappingUpdater->ensureProductStockLocationMappingsExistForStockIds($stockIds);
            $this->productStockLocationConfigurationUpdater->recalculateStockBelowReorderPointForStockIds($stockIds);
            $this->cleanUpStocks($productIds, $context);
            $this->recalculateProductPhysicalStock($productIds, $context);
            $this->batchStockMappingService->cleanUpBatchMappings();
        });

        $stockMovementPayloads = array_map(
            fn(StockMovement $stockMovement) => $stockMovement->toPayload(),
            $stockMovements,
        );
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'stock-recalculated-from-stock-movements',
            [
                'stockMovementIds' => array_column($stockMovementPayloads, 'id'),
                'batchManagementEnabled' => true,
            ],
        );
        $this->eventDispatcher->dispatch(new StockUpdatedForStockMovementsEvent($stockMovementPayloads, $context));
    }

    /**
     * Collapses stock movements by product, source, and destination, summing quantities. Stock movements for
     * batch-managed products are only collapsed if they contain batch information.
     *
     * @param list<StockMovement> $stockMovements
     * @return array{0: list<StockMovement>, 1: list<StockMovement>} collapsed stock movements and remaining stock movements for batch-managed products without batch information
     */
    private function collapseStockMovements(array $stockMovements, Context $context): array
    {
        $batchManagedStockMovements = $this->batchStockMappingService->filterToBatchManagedStockMovements($stockMovements, $context);
        $batchManagedProductIds = array_map(fn(StockMovement $stockMovement) => $stockMovement->getProductId(), $batchManagedStockMovements);

        $collapsedStockMovementPayloads = [];
        $stockMovementsWithMissingBatchInformation = [];
        foreach ($stockMovements as $stockMovement) {
            $isBatchManagedProduct = in_array($stockMovement->getProductId(), $batchManagedProductIds, true);
            if ($isBatchManagedProduct && $stockMovement->getBatches() === null) {
                $stockMovementsWithMissingBatchInformation[] = $stockMovement;

                continue;
            }

            $key = sprintf(
                '%s-%s-%s',
                $stockMovement->getProductId(),
                $stockMovement->getSource()->hash(),
                $stockMovement->getDestination()->hash(),
            );

            $collapsedStockMovementPayloads[$key] ??= [
                'productId' => $stockMovement->getProductId(),
                'source' => $stockMovement->getSource(),
                'destination' => $stockMovement->getDestination(),
                'quantity' => 0,
                'batches' => $isBatchManagedProduct ? new CountingMap() : null,
            ];
            $collapsedStockMovementPayloads[$key]['quantity'] += $stockMovement->getQuantity();
            if ($isBatchManagedProduct) {
                foreach ($stockMovement->getBatches() as $batchId => $quantity) {
                    $collapsedStockMovementPayloads[$key]['batches']->add($batchId, $quantity);
                }
            }
        }

        return [
            array_values(array_map(StockMovement::create(...), $collapsedStockMovementPayloads)),
            $stockMovementsWithMissingBatchInformation,
        ];
    }

    public function productWarehouseConfigurationWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $writeResults = $entityWrittenEvent->getWriteResults();
        $oldProductBinLocations = [];
        $newProductBinLocations = [];
        foreach ($writeResults as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            $payload = $writeResult->getPayload();
            if ($changeSet && $changeSet->hasChanged('default_bin_location_id')) {
                $productId = $changeSet->getBefore('product_id');
                $oldDefaultBinLocationId = $changeSet->getBefore('default_bin_location_id');
                if ($oldDefaultBinLocationId) {
                    $oldProductBinLocations[] = new ProductBinLocation(bin2hex($productId), bin2hex($oldDefaultBinLocationId));
                }

                $newDefaultBinLocationId = $changeSet->getAfter('default_bin_location_id');
                if ($newDefaultBinLocationId) {
                    $newProductBinLocations[] = new ProductBinLocation(bin2hex($productId), bin2hex($newDefaultBinLocationId));
                }
            } elseif ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $defaultBinLocationId = $payload['defaultBinLocationId'] ?? null;
                if ($defaultBinLocationId) {
                    $newProductBinLocations[] = new ProductBinLocation($payload['productId'], $defaultBinLocationId);
                }
            }
        }

        $this->deleteStockEntriesForOldDefaultBinLocations($oldProductBinLocations, $entityWrittenEvent->getContext());

        $this->upsertStockEntriesForDefaultBinLocations($newProductBinLocations);
    }

    /**
     * @param string[] $productIds
     */
    public function upsertStockEntriesForDefaultBinLocationsOfProducts(array $productIds): void
    {
        $configurations = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(product_id)) AS productId,
                LOWER(HEX(default_bin_location_id)) AS binLocationId
            FROM pickware_erp_product_warehouse_configuration
            WHERE product_id IN (:productIds)
                AND product_version_id = :liveVersionId
                AND default_bin_location_id IS NOT NULL',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
            ],
        );

        $productBinLocations = array_map(fn(array $configuration) => new ProductBinLocation($configuration['productId'], $configuration['binLocationId']), $configurations);

        $this->upsertStockEntriesForDefaultBinLocations($productBinLocations);
    }

    /**
     * @param ProductBinLocation[] $productBinLocations
     * @throws Exception
     */
    private function upsertStockEntriesForDefaultBinLocations(array $productBinLocations): void
    {
        $stockIds = array_map(fn() => Uuid::randomHex(), range(1, count($productBinLocations)));
        if (count($productBinLocations) > 0) {
            $tuples = implode(', ', array_map(fn(ProductBinLocation $productBinLocation, string $id) => sprintf(
                '(UNHEX(\'%s\'), UNHEX(\'%s\'), UNHEX(\'%s\'), "%s", UNHEX(\'%s\'), 0, UTC_TIMESTAMP(3))',
                $id,
                $productBinLocation->getProductId(),
                Defaults::LIVE_VERSION,
                LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                $productBinLocation->getBinLocationId(),
            ), $productBinLocations, $stockIds));

            $query = sprintf(
                'INSERT IGNORE INTO `pickware_erp_stock`
                (
                    `id`,
                    `product_id`,
                    `product_version_id`,
                    `location_type_technical_name`,
                    `bin_location_id`,
                    `quantity`,
                    `created_at`
                ) VALUES %s',
                $tuples,
            );

            $this->db->executeStatement($query);
            $this->productStockLocationMappingUpdater->ensureProductStockLocationMappingsExistForStockIds($stockIds);
        }
    }

    /**
     * Deletes stock entries for the given default bin location and products if it has no stock.
     *
     * @param ProductBinLocation[] $productBinLocations
     * @throws Exception
     */
    private function deleteStockEntriesForOldDefaultBinLocations(array $productBinLocations, Context $context): void
    {
        if (count($productBinLocations) > 0) {
            $tuples = implode(', ', array_map(fn(ProductBinLocation $productBinLocation) => sprintf(
                '(UNHEX(\'%s\'), UNHEX(\'%s\'))',
                $productBinLocation->getProductId(),
                $productBinLocation->getBinLocationId(),
            ), $productBinLocations));

            $query = sprintf(
                'DELETE `pickware_erp_stock` FROM `pickware_erp_stock`
                WHERE `pickware_erp_stock`.`quantity` = 0
                AND `pickware_erp_stock`.`product_version_id` = :liveVersionId
                AND (`pickware_erp_stock`.`product_id`, `pickware_erp_stock`.`bin_location_id`) IN (%s)',
                $tuples,
            );

            $this->db->executeStatement(
                $query,
                ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
            );
        }
        $this->productStockLocationMappingUpdater->cleanupUnusedProductStockLocationMappingsForProductIds(
            array_map(fn(ProductBinLocation $productBinLocation) => $productBinLocation->getProductId(), $productBinLocations),
            $context,
        );
    }

    private function persistStockChange(string $productId, StockLocationReference $location, int $changeAmount): void
    {
        $this->persistStockChangePayload([
            'productId' => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
            'changeAmount' => $changeAmount,
            ...$location->toPayload(),
        ]);
    }

    /**
     * @param array{
     *     locationTypeTechnicalName: string,
     *     productId: string,
     *     productVersionId: string,
     *     warehouseId?: string,
     *     binLocationId?: string,
     *     orderId?: string,
     *     orderVersionId?: string,
     *     stockContainerId?: string,
     *     returnOrderId?: string,
     *     returnOrderVersionId?: string,
     *     goodsReceiptId?: string,
     *     specialStockLocationTechnicalName?: string,
     *     changeAmount: int,
     * } $payload
     */
    private function persistStockChangePayload(array $payload): void
    {
        $parameters = [
            'id' => Uuid::randomBytes(),
            'locationTypeTechnicalName' => $payload['locationTypeTechnicalName'],
            'productId' => hex2bin($payload['productId']),
            'productVersionId' => hex2bin($payload['productVersionId']),
            'warehouseId' => doIf($payload['warehouseId'], hex2bin(...)),
            'binLocationId' => doIf($payload['binLocationId'], hex2bin(...)),
            'orderId' => doIf($payload['orderId'], hex2bin(...)),
            'orderVersionId' => doIf($payload['orderVersionId'], hex2bin(...)),
            'stockContainerId' => doIf($payload['stockContainerId'], hex2bin(...)),
            'returnOrderId' => doIf($payload['returnOrderId'], hex2bin(...)),
            'returnOrderVersionId' => doIf($payload['returnOrderVersionId'], hex2bin(...)),
            'goodsReceiptId' => doIf($payload['goodsReceiptId'], hex2bin(...)),
            'specialStockLocationTechnicalName' => $payload['specialStockLocationTechnicalName'] ?? null,
            'changeAmount' => $payload['changeAmount'],
        ];

        // First, attempt an UPDATE. This avoids acquiring a gap lock, which can lead to deadlocks,
        // as it only locks rows that already exist.
        // See https://github.com/pickware/shopware-plugins/issues/8782
        $affectedRows = $this->db->executeStatement(
            'UPDATE `pickware_erp_stock`
            SET `quantity` = `quantity` + :changeAmount,
                `updated_at` = UTC_TIMESTAMP(3)
            WHERE
                `product_id` = :productId
                AND `product_version_id` = :productVersionId
                AND `location_type_technical_name` = :locationTypeTechnicalName
                AND `warehouse_id` <=> :warehouseId
                AND `bin_location_id` <=> :binLocationId
                AND `order_id` <=> :orderId
                AND `stock_container_id` <=> :stockContainerId
                AND `return_order_id` <=> :returnOrderId
                AND `goods_receipt_id` <=> :goodsReceiptId
                AND `special_stock_location_technical_name` <=> :specialStockLocationTechnicalName',
            $parameters,
        );

        // If no row was updated, the row does not exist, so we try the insert.
        // The INSERT ... ON DUPLICATE KEY UPDATE is still used to handle the edge case
        // where two transactions might simultaneously decide to write exactly the same location from 0 to another value.
        if ($affectedRows === 0) {
            $this->db->executeStatement(
                'INSERT INTO `pickware_erp_stock` (
                    `id`,
                    `product_id`,
                    `product_version_id`,
                    `quantity`,
                    `location_type_technical_name`,
                    `warehouse_id`,
                    `bin_location_id`,
                    `order_id`,
                    `order_version_id`,
                    `stock_container_id`,
                    `return_order_id`,
                    `return_order_version_id`,
                    `goods_receipt_id`,
                    `special_stock_location_technical_name`,
                    `created_at`
                ) VALUES (
                    :id,
                    :productId,
                    :productVersionId,
                    :changeAmount,
                    :locationTypeTechnicalName,
                    :warehouseId,
                    :binLocationId,
                    :orderId,
                    :orderVersionId,
                    :stockContainerId,
                    :returnOrderId,
                    :returnOrderVersionId,
                    :goodsReceiptId,
                    :specialStockLocationTechnicalName,
                    UTC_TIMESTAMP(3)
                ) ON DUPLICATE KEY UPDATE
                    `quantity` = `quantity` + :changeAmount,
                    `updated_at` = UTC_TIMESTAMP(3)',
                $parameters,
            );
        }
    }

    /**
     * Clears (deletes) stock values that are irrelevant. These are stocks that
     *   - have quantity 0 or
     *   - are in any non-special stock location that was deleted
     * Also deletes product stock mappings that have no associated stocks or configurations.
     * @param string[] $productIds
     */
    private function cleanUpStocks(array $productIds, Context $context): void
    {
        $this->db->executeStatement(
            'DELETE `stock`
            FROM `pickware_erp_stock` AS `stock`
            LEFT JOIN `pickware_erp_product_warehouse_configuration` AS `product_warehouse_configuration`
                ON `stock`.`product_id` = `product_warehouse_configuration`.product_id
                    AND `stock`.`bin_location_id` = `product_warehouse_configuration`.`default_bin_location_id`
            WHERE
                (
                    `stock`.`quantity` = 0 OR
                    (`stock`.`location_type_technical_name` = "warehouse" AND `stock`.`warehouse_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "bin_location" AND `stock`.`bin_location_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "order" AND `stock`.`order_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "stock_container" AND `stock`.`stock_container_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "return_order" AND `stock`.`return_order_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "goods_receipt" AND `stock`.`goods_receipt_id` IS NULL)
                )
            AND `stock`.`product_version_id` = :liveVersionId
            AND `stock`.`product_id` IN (:productIds)
            AND `product_warehouse_configuration`.`default_bin_location_id` IS NULL
            ',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'productIds' => array_map('hex2bin', $productIds),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
            ],
        );

        $this->productStockLocationMappingUpdater->cleanupUnusedProductStockLocationMappingsForProductIds($productIds, $context);
    }

    /**
     * @param string[] $productIds
     */
    private function recalculateProductPhysicalStock(array $productIds, Context $context): void
    {
        $query = '
            UPDATE `pickware_erp_pickware_product` AS `pickware_product`
            LEFT JOIN (
                SELECT
                    `stock`.`product_id` as `product_id`,
                    `stock`.`product_version_id` as `product_version_id`,
                    SUM(`stock`.`quantity`) AS `quantity`
                FROM `pickware_erp_stock` `stock`
                LEFT JOIN `pickware_erp_location_type` AS `location_type`
                    ON `stock`.`location_type_technical_name` = `location_type`.`technical_name`
                WHERE `location_type`.`internal` = 1
                AND `stock`.`product_id` IN (:productIds) AND `stock`.`product_version_id` = :liveVersionId
                GROUP BY
                    `stock`.`product_id`,
                    `stock`.`product_version_id`
            ) AS `totalStocks`
                ON
                    `totalStocks`.`product_id` = `pickware_product`.`product_id`
                    AND `totalStocks`.`product_version_id` = `pickware_product`.`product_version_id`
            SET
                `pickware_product`.`physical_stock` = IFNULL(`totalStocks`.`quantity`, 0)
            WHERE
                `pickware_product`.`product_version_id` = :liveVersionId
                AND `pickware_product`.`product_id` IN (:productIds)';

        $params = [
            'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            'productIds' => array_map('hex2bin', $productIds),
        ];
        $paramTypes = [
            'productIds' => ArrayParameterType::STRING,
        ];
        $this->db->executeStatement($query, $params, $paramTypes);

        $this->eventDispatcher->dispatch(new ProductPhysicalStockUpdatedEvent($productIds, $context));
    }
}
