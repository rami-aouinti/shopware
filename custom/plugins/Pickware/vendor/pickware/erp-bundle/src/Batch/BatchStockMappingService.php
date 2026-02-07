<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMappingCollection;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMappingDefinition;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMappingEntity;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMovementMappingDefinition;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMovementMappingOrigin;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationConfigurationService;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class BatchStockMappingService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
        private readonly StockLocationConfigurationService $stockLocationConfigurationService,
    ) {}

    /**
     * @param list<StockMovement> $stockMovements
     */
    public function processBatchStockMappingChangeOfStockMovements(array $stockMovements, Context $context): void
    {
        foreach ($stockMovements as $stockMovement) {
            $batches = $stockMovement->getBatches();
            if ($batches === null) {
                continue;
            }

            foreach ($batches as $batchId => $batchQuantity) {
                $this->persistBatchStockMappingQuantityChange(
                    batchId: $batchId,
                    productId: $stockMovement->getProductId(),
                    location: $stockMovement->getSource(),
                    quantityChangeAmount: -1 * $batchQuantity,
                );

                $this->persistBatchStockMappingQuantityChange(
                    batchId: $batchId,
                    productId: $stockMovement->getProductId(),
                    location: $stockMovement->getDestination(),
                    quantityChangeAmount: 1 * $batchQuantity,
                );
            }
        }

        $this->throwIfNegativeBatchStockMappingsExist($stockMovements, $context);
        $this->throwIfBinLocationWithMultipleBatchesForSameProductExists($stockMovements, $context);
    }

    public function processStockMovementWithConservativeBatchStockMappingInference(
        StockMovement $stockMovement,
        Context $context,
    ): void {
        // Special stock locations are batch-void. Hence, there is nothing to infer from them.
        if ($stockMovement->getSource()->isSpecialStockLocation()) {
            return;
        }

        /** @var StockEntity $sourceStock */
        $sourceStock = $this->entityManager->getOneBy(
            StockDefinition::class,
            (new Criteria())
                ->addFilter(new EqualsFilter('productId', $stockMovement->getProductId()))
                ->addFilter($stockMovement->getSource()->getFilterForStockDefinition()),
            $context,
            ['batchMappings'],
        );
        foreach ($sourceStock->getBatchMappings() as $batchMappingOfSourceStock) {
            // When moving stock without batch information we must assume, for all batches, that the entire moved
            // stock quantity was part of the batch in question.
            // Hence, we reduce every batch quantity at a stock location by the quantity of the stock movement.
            $this->persistBatchStockMappingQuantityChange(
                batchId: $batchMappingOfSourceStock->getBatchId(),
                productId: $stockMovement->getProductId(),
                location: $stockMovement->getSource(),
                quantityChangeAmount: -1 * min(
                    $batchMappingOfSourceStock->getQuantity(),
                    $stockMovement->getQuantity(),
                ),
            );

            // When moving stock without batch information, we must only add the quantity to the destination stock
            // that we can be absolutely certain must have been moved there.
            // We can only be certain that a batch stock mapping of a stock has been increased, if the moved quantity
            // is greater than the quantity that was already occupied by other batches (including the unknown
            // batch quantity).
            // Depends on:
            //   - the presence of up-to-date source stock after the stock movement is processed,
            //   - the presence of the source stock before the stock movement is processed (but the source stock might
            //     not exist before the stock movement is processed so we circumvent this by adding the stock movement
            //     quantity to the source stock quantity)
            //   - the presence of up-to-date destination stock after the stock movement is processed
            $totalSourceStockQuantity = $sourceStock->getQuantity() + $stockMovement->getQuantity();
            $stockQuantityAlreadyOccupiedByOtherBatches = $totalSourceStockQuantity - $batchMappingOfSourceStock->getQuantity(
            );
            $upperBoundForMovedQuantityOfCurrentBatch = max(
                0,
                $stockMovement->getQuantity() - $stockQuantityAlreadyOccupiedByOtherBatches,
            );
            $guaranteedMovedQuantityOfCurrentBatch = min(
                $upperBoundForMovedQuantityOfCurrentBatch,
                $batchMappingOfSourceStock->getQuantity(),
            );
            if ($guaranteedMovedQuantityOfCurrentBatch === 0) {
                continue;
            }

            $this->persistBatchStockMappingQuantityChange(
                batchId: $batchMappingOfSourceStock->getBatchId(),
                productId: $stockMovement->getProductId(),
                location: $stockMovement->getDestination(),
                quantityChangeAmount: $guaranteedMovedQuantityOfCurrentBatch,
            );

            // Create BatchStockMovementMapping to track the pessimistically inferred batch movement
            $this->persistConservativelyInferredBatchStockMovementMapping(
                stockMovement: $stockMovement,
                batchId: $batchMappingOfSourceStock->getBatchId(),
                quantity: $guaranteedMovedQuantityOfCurrentBatch,
                context: $context,
            );
        }
    }

    private function persistConservativelyInferredBatchStockMovementMapping(
        StockMovement $stockMovement,
        string $batchId,
        int $quantity,
        Context $context,
    ): void {
        $this->entityManager->upsert(
            BatchStockMovementMappingDefinition::class,
            [
                [
                    'stockMovementId' => $stockMovement->getId(),
                    'productId' => $stockMovement->getProductId(),
                    'batchId' => $batchId,
                    'quantity' => $quantity,
                    'origin' => BatchStockMovementMappingOrigin::PessimisticallyInferred,
                ],
            ],
            $context,
        );
    }

    private function persistBatchStockMappingQuantityChange(
        string $batchId,
        string $productId,
        StockLocationReference $location,
        int $quantityChangeAmount,
    ): void {
        // Special stock locations are batch-void. Hence, we never apply batch information to them.
        if ($location->isSpecialStockLocation()) {
            return;
        }

        $locationPayload = $location->toPayload();
        $parameters = [
            'id' => Uuid::randomBytes(),
            'batchId' => hex2bin($batchId),
            'productId' => hex2bin($productId),
            'quantityChangeAmount' => $quantityChangeAmount,
            'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            'locationTypeTechnicalName' => $locationPayload['locationTypeTechnicalName'],
            'warehouseId' => doIf($locationPayload['warehouseId'], hex2bin(...)),
            'binLocationId' => doIf($locationPayload['binLocationId'], hex2bin(...)),
            'orderId' => doIf($locationPayload['orderId'], hex2bin(...)),
            'orderVersionId' => doIf($locationPayload['orderVersionId'], hex2bin(...)),
            'stockContainerId' => doIf($locationPayload['stockContainerId'], hex2bin(...)),
            'returnOrderId' => doIf($locationPayload['returnOrderId'], hex2bin(...)),
            'returnOrderVersionId' => doIf($locationPayload['returnOrderVersionId'], hex2bin(...)),
            'goodsReceiptId' => doIf($locationPayload['goodsReceiptId'], hex2bin(...)),
            'specialStockLocationTechnicalName' => $locationPayload['specialStockLocationTechnicalName'] ?? null,
        ];

        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_batch_stock_mapping` (
                `id`,
                `stock_id`,
                `batch_id`,
                `product_id`,
                `product_version_id`,
                `quantity`,
                `created_at`
            )
            SELECT
                :id,
                stock.id,
                :batchId,
                :productId,
                :liveVersionId,
                :quantityChangeAmount,
                UTC_TIMESTAMP(3)
            FROM `pickware_erp_stock` stock
            WHERE
                stock.product_id = :productId
                AND stock.location_type_technical_name = :locationTypeTechnicalName
                AND stock.warehouse_id <=> :warehouseId
                AND stock.bin_location_id <=> :binLocationId
                AND stock.order_id <=> :orderId
                AND stock.stock_container_id <=> :stockContainerId
                AND stock.return_order_id <=> :returnOrderId
                AND stock.goods_receipt_id <=> :goodsReceiptId
                AND stock.special_stock_location_technical_name <=> :specialStockLocationTechnicalName
            ON DUPLICATE KEY UPDATE
                `quantity` = `pickware_erp_batch_stock_mapping`.`quantity` + :quantityChangeAmount,
                `updated_at` = UTC_TIMESTAMP(3)',
            $parameters,
        );
    }

    public function cleanUpBatchMappings(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `pickware_erp_batch_stock_mapping`
            WHERE `quantity` <= 0
                AND `product_version_id` = :liveVersionId',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        );

        // Special stock locations are batch-void. Hence, we delete all batch stock mappings for them.
        $this->connection->executeStatement(
            'DELETE `batch_stock_mapping` FROM `pickware_erp_batch_stock_mapping` `batch_stock_mapping`
            INNER JOIN `pickware_erp_stock` `stock` ON `stock`.`id` = `batch_stock_mapping`.`stock_id`
            WHERE `stock`.`location_type_technical_name` = :specialStockLocationType',
            [
                'specialStockLocationType' => LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION,
            ],
        );
    }

    /**
     * @param list<StockMovement> $stockMovements
     * @return list<StockMovement>
     */
    public function filterToBatchManagedStockMovements(array $stockMovements, Context $context): array
    {
        /** @var string[] $batchManagedProductIds */
        $batchManagedProductIds = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            [
                'pickwareErpPickwareProduct.isBatchManaged' => true,
                'id' => array_map(fn(StockMovement $stockMovement) => $stockMovement->getProductId(), $stockMovements),
            ],
            $context,
        );

        return array_filter(
            $stockMovements,
            fn(StockMovement $stockMovement) => in_array($stockMovement->getProductId(), $batchManagedProductIds, true),
        );
    }

    /**
     * @param list<StockMovement> $stockMovements
     */
    private function throwIfNegativeBatchStockMappingsExist(array $stockMovements, Context $context): void
    {
        $criteria = $this->getCriteriaForBatchStockMappingThatMustNotBecomeNegative($stockMovements);
        if ($criteria === null) {
            return;
        }

        $criteria->addFilter(new RangeFilter('quantity', [RangeFilter::LT => 0]));

        /** @var BatchStockMappingCollection $negativeBatchStockMappings */
        $negativeBatchStockMappings = $this->entityManager->findBy(
            BatchStockMappingDefinition::class,
            $criteria,
            $context,
            [
                'stock.batchMappings',
                'product',
                'batch',
            ],
        );
        // Batch stock mappings are allowed to become negative if the negative quantity is compensated by the unknown
        // batch quantity.
        // Example:
        //   - Let product p1 have a quantity of 5 at location l1 and a batch b1 with quantity 3.
        //   - When moving 4 units from l1 to location l2 of batch b1, the quantity of b1 would become -1.
        //   - This stock movement is valid because the unknown batch quantity of p1 at l1 (i.e., 2) compensates for the
        //     negative quantity at the batch stock mapping (i.e., -1).
        $uncompensatedNegativeBatchStockMappings = $negativeBatchStockMappings
            ->filter(function(BatchStockMappingEntity $batchStockMapping) {
                $batchedQuantity = $batchStockMapping->getStock()->getBatchMappings()
                    ->filter(fn(BatchStockMappingEntity $batchMapping) => $batchMapping->getQuantity() > 0)
                    ->reduce(fn(int $sum, BatchStockMappingEntity $batchMapping) => $sum + $batchMapping->getQuantity(), 0);

                return $batchedQuantity > $batchStockMapping->getStock()->getQuantity();
            });

        if ($uncompensatedNegativeBatchStockMappings->count() === 0) {
            return;
        }

        $stockLocationConfigurations = $this->stockLocationConfigurationService->getStockLocationConfigurations(
            (new ImmutableCollection($uncompensatedNegativeBatchStockMappings))
                ->map(fn(BatchStockMappingEntity $batchStockMapping) => $batchStockMapping->getStock())
                ->map(fn(StockEntity $stock) => $stock->getStockLocationReference()),
            $context,
        );

        throw BatchStockMappingServiceValidationException::operationLeadsToNegativeBatchStock(
            $uncompensatedNegativeBatchStockMappings->map(fn(BatchStockMappingEntity $batchStockMapping) => [
                'stockLocationReference' => $batchStockMapping->getStock()->getStockLocationReference(),
                'productNumber' => $batchStockMapping->getProduct()->getProductNumber(),
                'batchNumber' => $batchStockMapping->getBatch()->getNumber(),
            ]),
            $stockLocationConfigurations,
        );
    }

    /**
     * @param array<StockMovement> $stockMovements
     */
    private function throwIfBinLocationWithMultipleBatchesForSameProductExists(array $stockMovements, Context $context): void
    {
        $productBinLocationTuples = (new ImmutableCollection($stockMovements))
            ->filter(
                fn(StockMovement $stockMovement) => $stockMovement->getBatches() !== null && $stockMovement->getDestination()->isBinLocation(),
            )
            ->map(fn(StockMovement $stockMovement) => [$stockMovement->getProductId(), $stockMovement->getDestination()->getBinLocationId()]);

        if (count($productBinLocationTuples) === 0) {
            // Return early as no valid SQL query can be generated without product bin location tuples.
            return;
        }

        $inList = implode(',', array_fill(0, count($productBinLocationTuples), '(?,?)'));
        $sql = <<<SQL
            SELECT
                LOWER(HEX(`stock`.`product_id`)) AS `productId`,
                LOWER(HEX(`stock`.`bin_location_id`)) AS `binLocationId`,
                `product`.`product_number` AS `productNumber`,
                count(`batch_stock_mapping`.`batch_id`) AS `count`
            FROM `pickware_erp_stock` `stock`
            INNER JOIN `product`
                ON `product`.`id` = `stock`.`product_id` AND `product`.`version_id` = `stock`.`product_version_id`
            INNER JOIN `pickware_erp_batch_stock_mapping` AS `batch_stock_mapping`
                ON `batch_stock_mapping`.`stock_id` = `stock`.`id`
            WHERE
                (`stock`.`product_id`, `stock`.`bin_location_id`) IN ({$inList})
                AND `stock`.`location_type_technical_name` = "bin_location"
                AND `stock`.product_version_id = ?
                -- Cleaning up empty batch stock mappings is done afterward, so we need to exclude "empty" batch stock
                -- mappings here
                AND `batch_stock_mapping`.`quantity` > 0
            GROUP BY `stock`.`product_id`, `stock`.`bin_location_id`, `product`.`product_number`
            HAVING count(`batch_stock_mapping`.`batch_id`) > 1
            SQL;

        $queryParameters = [
            ...$productBinLocationTuples->flatMap(fn(array $tuple) => [$tuple[0], $tuple[1]])->map('hex2bin')->asArray(),
            hex2bin(Defaults::LIVE_VERSION),
        ];
        $result = $this->connection->executeQuery($sql, $queryParameters)->fetchAllAssociative();

        if (count($result) === 0) {
            return;
        }

        $stockLocationConfigurations = $this->stockLocationConfigurationService->getStockLocationConfigurations(
            (new ImmutableCollection($result))->map(fn(array $row) => StockLocationReference::binLocation($row['binLocationId'])),
            $context,
        );

        throw BatchStockMappingServiceValidationException::binLocationContainsMultipleBatchesForSameProduct(
            array_map(fn(array $row) => [
                'productNumber' => $row['productNumber'],
                'stockLocationReference' => StockLocationReference::binLocation($row['binLocationId']),
            ], $result),
            $stockLocationConfigurations,
        );
    }

    /**
     * @param list<StockMovement> $stockMovements
     */
    private function getCriteriaForBatchStockMappingThatMustNotBecomeNegative(array $stockMovements): ?Criteria
    {
        $conditions = [];
        foreach ($stockMovements as $stockMovement) {
            if ($stockMovement->getSource()->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION) {
                continue;
            }
            if ($stockMovement->getBatches() === null) {
                continue;
            }

            foreach ($stockMovement->getBatches() as $batchId => $batchQuantity) {
                $conditions[] = new MultiFilter('AND', [
                    new EqualsFilter('productId', $stockMovement->getProductId()),
                    new EqualsFilter('batchId', $batchId),
                ]);
            }
        }

        if (count($conditions) === 0) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter('AND', [
            new MultiFilter('OR', $conditions),
            new EqualsFilter('product.pickwareErpPickwareProduct.isBatchManaged', true),
        ]));

        return $criteria;
    }
}
