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

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMappingDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class BatchStockAssignmentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly BatchStockUpdater $batchStockUpdater,
    ) {}

    public function assignStockToBatch(string $stockId, string $batchId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($stockId, $batchId, $context): void {
            $this->entityManager->lockPessimistically(StockDefinition::class, ['id' => $stockId], $context);
            /** @var StockEntity $stock */
            $stock = $this->entityManager->getByPrimaryKey(StockDefinition::class, $stockId, $context, ['batchMappings']);

            if ($stock->getBatchMappings()->count() > 1) {
                throw BatchException::stockHasMultipleBatchMappings($stockId);
            }
            if ($stock->getBatchMappings()->count() === 1 && $stock->getBatchMappings()->first()->getQuantity() !== $stock->getQuantity()) {
                throw BatchException::stockHasIncompleteBatchMapping($stockId);
            }

            $this->entityManager->upsert(
                BatchStockMappingDefinition::class,
                [
                    [
                        'id' => $stock->getBatchMappings()->first()?->getId() ?? Uuid::randomHex(),
                        'stockId' => $stockId,
                        'batchId' => $batchId,
                        'productId' => $stock->getProductId(),
                        'quantity' => $stock->getQuantity(),
                    ],
                ],
                $context,
            );
            $this->batchStockUpdater->calculateBatchStockForProducts([$stock->getProductId()]);
        });
    }

    public function changeStockBatchAssignment(
        string $productId,
        StockLocationReference $stockLocation,
        ?string $currentBatchId,
        string $newBatchId,
        int $quantityChangeAmount,
        Context $context,
    ): void {
        $this->entityManager->runInTransactionWithRetry(function() use ($productId, $stockLocation, $currentBatchId, $newBatchId, $quantityChangeAmount, $context): void {
            $stockIds = $this->entityManager->lockPessimistically(
                StockDefinition::class,
                (new Criteria())
                    ->addFilter(new EqualsFilter('productId', $productId))
                    ->addFilter($stockLocation->getFilterForStockDefinition()),
                $context,
            );
            if (count($stockIds) === 0) {
                throw new InvalidArgumentException('There is no stock for the given product and stock location.');
            }
            /** @var StockEntity $stock */
            $stock = $this->entityManager->getByPrimaryKey(
                StockDefinition::class,
                $stockIds[0],
                $context,
                ['batchMappings.batch'],
            );

            $batchMappingUpsertPayloads = [];
            if ($currentBatchId) {
                $currentBatchMapping = $stock->getBatchMappings()->filterByProperty('batchId', $currentBatchId)->first();
                if (!$currentBatchMapping) {
                    throw new InvalidArgumentException('There is no batch mapping for the given batch at the stock location.');
                }
                if ($quantityChangeAmount > $currentBatchMapping->getQuantity()) {
                    throw BatchException::insufficientBatchStockForAssignment(
                        batchId: $currentBatchMapping->getId(),
                        batchNumber: $currentBatchMapping->getBatch()->getNumber(),
                        currentQuantity: $currentBatchMapping->getQuantity(),
                    );
                }

                if ($currentBatchMapping->getQuantity() === $quantityChangeAmount) {
                    $this->entityManager->delete(
                        BatchStockMappingDefinition::class,
                        [$currentBatchMapping->getId()],
                        $context,
                    );
                } else {
                    $batchMappingUpsertPayloads[] = [
                        'id' => $currentBatchMapping->getId(),
                        'quantity' => $currentBatchMapping->getQuantity() - $quantityChangeAmount,
                    ];
                }
            } else {
                $unassignedQuantity = $stock->getQuantity() - $stock->getBatchMappings()->asBatchCountingMap()->getTotalCount();
                if ($quantityChangeAmount > $unassignedQuantity) {
                    throw BatchException::insufficientUnassignedStockForAssignment(
                        productId: $productId,
                        unassignedQuantity: $unassignedQuantity,
                    );
                }
            }

            $newBatchMapping = $stock->getBatchMappings()->filterByProperty('batchId', $newBatchId)->first();
            if ($newBatchMapping) {
                $batchMappingUpsertPayloads[] = [
                    'id' => $newBatchMapping->getId(),
                    'quantity' => $newBatchMapping->getQuantity() + $quantityChangeAmount,
                ];
            } else {
                $batchMappingUpsertPayloads[] = [
                    'id' => Uuid::randomHex(),
                    'stockId' => $stock->getId(),
                    'batchId' => $newBatchId,
                    'productId' => $stock->getProductId(),
                    'quantity' => $quantityChangeAmount,
                ];
            }

            $this->entityManager->upsert(BatchStockMappingDefinition::class, $batchMappingUpsertPayloads, $context);
            $this->batchStockUpdater->calculateBatchStockForProducts([$stock->getProductId()]);
        });
    }
}
