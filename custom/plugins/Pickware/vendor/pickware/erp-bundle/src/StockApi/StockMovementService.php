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

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Batch\BatchManagementDevFeatureFlag;
use Pickware\PickwareErpStarter\Batch\BatchStockUpdater;
use Pickware\PickwareErpStarter\Cache\CacheInvalidationService;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\PaperTrail\ErpPaperTrailUri;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailUriProvider;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductCollection;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Stock\ProductStockUpdater;
use Pickware\PickwareErpStarter\Stock\ProductStockUpdaterValidationException;
use Pickware\PickwareErpStarter\Stock\StockNotAvailableForSaleUpdater;
use Pickware\PickwareErpStarter\Stock\WarehouseStockUpdater;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class StockMovementService
{
    private bool $allowNegativeStocks = false;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockLocationSnapshotGenerator $stockLocationSnapshotGenerator,
        private readonly FeatureFlagService $featureFlagService,
        private readonly StockLocationConfigurationService $stockLocationConfigurationService,
        private readonly CacheInvalidationService $cacheInvalidationService,
        private readonly StockNotAvailableForSaleUpdater $stockNotAvailableForSaleUpdater,
        private readonly WarehouseStockUpdater $warehouseStockUpdater,
        private readonly ProductStockUpdater $productStockUpdater,
        private readonly BatchStockUpdater $batchStockUpdater,
        private readonly PaperTrailUriProvider $paperTrailUriProvider,
        private readonly PaperTrailLoggingService $paperTrailLoggingService,
    ) {}

    public function allowNegativeStocks(callable $callback): void
    {
        $this->allowNegativeStocks = true;

        try {
            $callback();
        } finally {
            $this->allowNegativeStocks = false;
        }
    }

    /**
     * @deprecated Will be removed in 4.0.0 Use allowNegativeStocks instead
     */
    public function ignoreNegativeStock(callable $callback): void
    {
        $this->allowNegativeStocks($callback);
    }

    /**
     * @param StockMovement[] $stockMovements
     */
    public function moveStock(array $stockMovements, Context $context): void
    {
        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('stock-moved'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Stock move triggered',
            [
                'stockMovements' => $stockMovements,
            ],
        );
        $locations = array_merge([], ...array_map(fn(StockMovement $stockMovement) => [
            $stockMovement->getSource(),
            $stockMovement->getDestination(),
        ], $stockMovements));

        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            $containsReturnOrderLocation = count(array_filter(
                $locations,
                fn(StockLocationReference $location) => $location->isReturnOrder(),
            )) !== 0;

            if ($containsReturnOrderLocation) {
                throw new InvalidArgumentException(
                    'Return order locations are not allowed in stock movements when feature flag "goods-receipt-for-return-order" is active.',
                );
            }
        }

        $this->stockLocationSnapshotGenerator->generateSnapshots($locations, $context);

        // If no user responsible for the stock movement is set already, determine the user from the context if possible
        foreach ($stockMovements as $stockMovement) {
            if (!$stockMovement->getUserId()) {
                $stockMovement->setUserId($this->getUserIdFromAdminApiContext($context));
            }
        }

        // Validation before acquiring any locks and writing stock movements
        $this->validateSourceAndDestinationLocationTypes($stockMovements);
        $this->throwIfStockIsMovedIntoBinLocationForNonStockManagedProducts($stockMovements, $context);

        $criteria = $this->getCriteriaForSourceLocationThatMustNotBecomeNegative($stockMovements);
        $this->entityManager->runInTransactionWithRetry(
            function() use ($stockMovements, $context, $criteria): void {
                if ($criteria !== null) {
                    $this->entityManager->lockPessimistically(StockDefinition::class, $criteria, $context);
                }

                $context->scope(
                    Context::SYSTEM_SCOPE,
                    function(Context $context) use ($stockMovements): void {
                        $stockMovementPayloads = array_map(
                            fn(StockMovement $stockMovement) => $stockMovement->toPayload(),
                            $stockMovements,
                        );

                        // Use array_values if (e.g. due to filtering) the input stock movements did not have strict ascending numerical array keys
                        $this->entityManager->create(
                            StockMovementDefinition::class,
                            array_values($stockMovementPayloads),
                            $context,
                        );
                    },
                );

                $productIds = array_values(array_unique(array_map(
                    fn(StockMovement $stockMovement) => $stockMovement->getProductId(),
                    $stockMovements,
                )));
                $this->cacheInvalidationService->invalidateProductCache($productIds);
                $stockMovementIds = array_map(
                    fn(StockMovement $stockMovement) => $stockMovement->getId(),
                    $stockMovements,
                );
                $this->stockNotAvailableForSaleUpdater->updateProductStockNotAvailableForSaleByStockMovements(
                    $stockMovementIds,
                    $context,
                );
                $this->warehouseStockUpdater->indexStockMovements($stockMovementIds, $context);
                try {
                    $this->productStockUpdater->recalculateStockFromStockMovements($stockMovements, $context);
                } catch (ProductStockUpdaterValidationException $exception) {
                    throw StockMovementServiceValidationException::fromProductStockUpdaterValidationException($exception);
                }
                if ($this->featureFlagService->isActive(BatchManagementDevFeatureFlag::NAME)) {
                    $this->batchStockUpdater->calculateBatchStockForProducts($productIds);
                }

                if (!$this->allowNegativeStocks) {
                    $this->throwIfNegativeStockLocationsExist($stockMovements, $context);
                }
            },
        );
        $this->paperTrailLoggingService->logPaperTrailEvent('Stock moved successfully');
        $this->paperTrailUriProvider->reset();
    }

    /**
     * @param StockMovement[] $stockMovements
     */
    private function throwIfNegativeStockLocationsExist(array $stockMovements, Context $context): void
    {
        $criteria = $this->getCriteriaForSourceLocationThatMustNotBecomeNegative($stockMovements);

        if ($criteria === null) {
            return;
        }

        $criteria->addFilter(new RangeFilter('quantity', [RangeFilter::LT => 0]));

        /** @var StockCollection $negativeStocks */
        $negativeStocks = $this->entityManager->findBy(
            StockDefinition::class,
            $criteria,
            $context,
            ['product'],
        );
        if ($negativeStocks->count() === 0) {
            return;
        }

        $stockLocationConfigurations = $this->stockLocationConfigurationService->getStockLocationConfigurations(
            $negativeStocks
                ->getProductQuantityLocations()
                ->map(fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()),
            $context,
        );

        throw new OperationLeadsToNegativeStocksException(
            negativeStocks: $negativeStocks,
            stockLocationConfigurations: $stockLocationConfigurations,
        );
    }

    /**
     * @param StockMovement[] $stockMovements
     */
    private function getCriteriaForSourceLocationThatMustNotBecomeNegative(array $stockMovements): ?Criteria
    {
        if ($this->allowNegativeStocks) {
            // If negative stocks are allowed, every stock location is allowed to become negative
            return null;
        }

        $conditions = [];
        foreach ($stockMovements as $stockMovement) {
            $sourceLocation = $stockMovement->getSource();
            if ($sourceLocation->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION) {
                continue;
            }
            $conditions[] = new MultiFilter('AND', [
                new EqualsFilter('productId', $stockMovement->getProductId()),
                $sourceLocation->getFilterForStockDefinition(),
            ]);
        }

        if (count($conditions) === 0) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter('AND', [
            new MultiFilter('OR', $conditions),
            new EqualsFilter('product.pickwareErpPickwareProduct.isStockManagementDisabled', false),
        ]));

        return $criteria;
    }

    private function getUserIdFromAdminApiContext(Context $context): ?string
    {
        $contextSource = $context->getSource();
        if ($contextSource instanceof AdminApiSource) {
            return $contextSource->getUserId();
        }

        return null;
    }

    /**
     * Validates all source and destination stock location types for valid pairs. Throws a
     * StockMovementServiceValidationException when an invalid combination of stock locations is used.
     *
     * @param StockMovement[] $stockMovements
     */
    private function validateSourceAndDestinationLocationTypes(array $stockMovements): void
    {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            // The validation is not necessary anymore because only stock movements to return orders were validated at
            // all and there are not allowed anymore.
            return;
        }

        $invalidCombinations = [];
        foreach ($stockMovements as $stockMovement) {
            $source = $stockMovement->getSource()->getLocationTypeTechnicalName();
            $destination = $stockMovement->getDestination()->getLocationTypeTechnicalName();

            if ($destination === LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER) {
                if ($stockMovement->getSource()->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_ORDER) {
                    continue;
                }
                if (
                    $stockMovement->getSource()->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION
                    && $stockMovement->getSource()->getPrimaryKey() === SpecialStockLocationDefinition::TECHNICAL_NAME_UNKNOWN
                ) {
                    continue;
                }

                $invalidCombinations[] = [
                    'source' => $source,
                    'destination' => $destination,
                ];
            }
        }

        if (count($invalidCombinations) > 0) {
            throw StockMovementServiceValidationException::invalidCombinationOfSourceAndDestinationStockLocations(
                $invalidCombinations,
            );
        }
    }

    /**
     * Validates that no stock movement is written for not-stock-managed products into a bin location.
     *
     * @param StockMovement[] $stockMovements
     */
    public function throwIfStockIsMovedIntoBinLocationForNonStockManagedProducts(array $stockMovements, Context $context): void
    {
        $productIdsWithBinLocationDestination = array_unique(array_filter(array_map(
            function(StockMovement $stockMovement): ?string {
                if ($stockMovement->getDestination()->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION) {
                    return $stockMovement->getProductId();
                }

                return null;
            },
            $stockMovements,
        )));
        if (count($productIdsWithBinLocationDestination) === 0) {
            return;
        }
        /** @var PickwareProductCollection $products */
        $pickwareProducts = $this->entityManager->findBy(
            PickwareProductDefinition::class,
            ['productId' => $productIdsWithBinLocationDestination],
            $context,
        );
        $notStockManagedProductIds = array_unique($pickwareProducts->fmap(
            fn(PickwareProductEntity $pickwareProduct) => $pickwareProduct->getIsStockManagementDisabled() ? $pickwareProduct->getProductId() : null,
        ));
        if (count($notStockManagedProductIds) > 0) {
            throw StockMovementServiceValidationException::operationMovesStockToBinLocationsForNotStockManagedProducts($notStockManagedProductIds);
        }
    }
}
