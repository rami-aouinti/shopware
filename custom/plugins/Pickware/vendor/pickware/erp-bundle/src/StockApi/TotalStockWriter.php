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

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Picking\ProductOrthogonalPickingStrategy;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stocking\ProductOrthogonalStockingStrategy;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;

class TotalStockWriter
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductOrthogonalPickingStrategy $pickingStrategy,
        private readonly StockMovementService $stockMovementService,
        private readonly StockingStrategy $stockingStrategy,
        private readonly Config $config,
    ) {}

    /**
     * @param array<string, int> $productStocks Associative array with [productId] => newStock
     * @param StockLocationReference $externalLocation External location where the corresponding stock gets
     *        removed/added
     */
    public function setTotalStockForProducts(
        array $productStocks,
        StockLocationReference $externalLocation,
        Context $context,
    ): void {
        if (count($productStocks) === 0) {
            return;
        }
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $this->entityManager->runInTransactionWithRetry(
            function() use ($externalLocation, $productStocks, $context): void {
                $criteria = EntityManager::createCriteriaFromArray([
                    'locationType.internal' => true,
                    'productId' => array_keys($productStocks),
                ]);
                $this->entityManager->lockPessimistically(StockDefinition::class, $criteria, $context);
                $criteria->addAggregation(
                    new TermsAggregation(
                        'products',
                        'productId',
                        null,
                        null,
                        new SumAggregation('quantity', 'quantity'),
                    ),
                );
                /** @var TermsResult $aggregationResult */
                $aggregationResult = $this->entityManager
                    ->getRepository(StockDefinition::class)
                    ->aggregate($criteria, $context)
                    ->get('products');
                $currentProductStockByProductId = [];
                // There is one $bucket for each product
                foreach ($aggregationResult->getBuckets() as $bucket) {
                    $productId = $bucket->getKey();
                    /** @var SumResult $sumOfStucks */
                    $sumOfStucks = $bucket->getResult();
                    $currentProductStockByProductId[$bucket->getKey()] = (int) $sumOfStucks->getSum();
                }

                $stockMovements = [];
                foreach ($productStocks as $productId => $stock) {
                    if (array_key_exists($productId, $currentProductStockByProductId)) {
                        $currentStock = $currentProductStockByProductId[$productId];
                    } else {
                        $currentStock = 0;
                    }
                    $stockChange = $stock - $currentStock;

                    if ($stockChange > 0) {
                        $stockingRequest = new StockingRequest(
                            productQuantities: new ProductQuantityImmutableCollection(
                                [new ProductQuantity($productId, $stockChange)],
                            ),
                            stockArea: StockArea::everywhere(),
                        );
                        $productQuantityLocations = $this->stockingStrategy->calculateStockingSolution($stockingRequest, $context);
                        $stockMovements[] = $productQuantityLocations->createStockMovementsWithSource($externalLocation);
                    } elseif ($stockChange < 0) {
                        try {
                            $pickingSolution = $this->pickingStrategy->calculatePickingSolution(
                                pickingRequest: new PickingRequest(
                                    new ProductQuantityImmutableCollection(
                                        [
                                            new ProductQuantity(
                                                productId: $productId,
                                                quantity: -1 * $stockChange,
                                            ),
                                        ],
                                    ),
                                    StockArea::everywhere(),
                                ),
                                context: $context,
                            );
                            $stockMovements[] = $pickingSolution->createStockMovementsWithDestination($externalLocation);
                        } catch (PickingStrategyStockShortageException $stockShortageException) {
                            // When there is not enough pickable stock, the stock shortage is taken from the unknown
                            // location in the default warehouse. We allow the stock to become negative here, The
                            // StockMovementService will fail if the stock becomes negative and it is not allowed. The
                            // negative stock is then handled there.
                            $defaultWarehouseId = $this->config->getDefaultWarehouseId();
                            $stockMovements[] = $stockShortageException->getPartialPickingRequestSolution()->merge(
                                $stockShortageException
                                    ->getStockShortages()
                                    ->map(
                                        fn(ProductQuantity $productQuantity) => new ProductQuantityLocation(
                                            StockLocationReference::warehouse($defaultWarehouseId),
                                            $productQuantity->getProductId(),
                                            $productQuantity->getQuantity(),
                                        ),
                                        returnType: ProductQuantityLocationImmutableCollection::class,
                                    ),
                            )->createStockMovementsWithDestination($externalLocation);
                        }
                    } else {
                        continue;
                    }

                    // Performance optimization:
                    // In case both strategies (for picking and stocking) are product-orthogonal we can collect all
                    // emerging stock-movements and write them together in one call of moveStock.
                    // Otherwise, we need to write each emerging stock movement immediately.
                    if (
                        !($this->stockingStrategy instanceof ProductOrthogonalStockingStrategy)
                        || !($this->pickingStrategy instanceof ProductOrthogonalPickingStrategy)
                    ) {
                        $this->writeStockMovements($stockMovements, $productStocks, $context);
                        $stockMovements = [];
                    }
                }
                if (count($stockMovements) > 0) {
                    $this->writeStockMovements($stockMovements, $productStocks, $context);
                }
            },
        );
    }

    private function writeStockMovements(array $stockMovements, array $productStocks, Context $context): void
    {
        try {
            $this->stockMovementService->moveStock(array_merge(...$stockMovements), $context);
        } catch (OperationLeadsToNegativeStocksException $e) {
            // Pass through only the first product because otherwise the error message would be too long
            $productId = $e->getProductIds()[0];
            $stock = $productStocks[$productId];
            if ($stock < 0) {
                throw TotalStockWriterException::negativeStockNotAllowed($productId, $e);
            }

            throw TotalStockWriterException::notEnoughStock($productId, $e);
        }
    }
}
