<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Picking\ProductOrthogonalPickingStrategy;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\StockApi\StockMovementServiceValidationException;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
class StockImporter implements Importer
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockMovementService $stockMovementService,
        private readonly StockImportCsvRowNormalizer $normalizer,
        private readonly StockImportLocationFinder $stockImportLocationFinder,
        private readonly ImportExportStateService $importExportStateService,
        private readonly ProductOrthogonalPickingStrategy $pickingStrategy,
        private readonly StockingStrategy $stockingStrategy,
        private readonly StockChangeCalculator $stockChangeCalculator,
        private readonly Validator $validator,
        private readonly StockLimitConfigurationUpdater $stockLimitConfigurationUpdater,
        private readonly int $batchSize,
    ) {}

    public function canBeParallelized(): bool
    {
        return false;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        $errors = $this->validator->validateHeaderRow($headerRow, $context);

        $actualColumns = $this->normalizer->normalizeColumnNames($headerRow);
        if (
            in_array('binLocationCode', $actualColumns, true)
            && !in_array('warehouseCode', $actualColumns, true)
            && !in_array('warehouseName', $actualColumns, true)
        ) {
            $errors->addError(StockImportException::createWarehouseForBinLocationMissing());
        }

        return $errors;
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
        /** @var ImportExportEntity $import */
        $import = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $importId, $context);

        $criteria = EntityManager::createCriteriaFromArray(['importExportId' => $importId]);
        $criteria->addFilter(new RangeFilter('rowNumber', [
            RangeFilter::GTE => $nextRowNumberToRead,
            RangeFilter::LT => $nextRowNumberToRead + $this->batchSize,
        ]));

        /** @var ImportExportElementCollection $importElements */
        $importElements = $this->entityManager->findBy(
            ImportExportElementDefinition::class,
            $criteria,
            $context,
        );

        if ($importElements->count() === 0) {
            return null;
        }

        $normalizedRows = $importElements->map(fn(ImportExportElementEntity $importElement) => $this->normalizer->normalizeRow($importElement->getRowData()));
        $productNumberIdMapping = $this->getProductNumberIdMapping($normalizedRows, $context);
        // Mapping: normalizedColumnName => originalColumnName
        $normalizedToOriginalColumnNameMapping = $this->normalizer->mapNormalizedToOriginalColumnNames(array_keys(
            $importElements->first()->getRowData(),
        ));

        $pickwareProductsByProductId = [];
        $productWarehouseConfigurationsByConcatenatedProductAndWarehouseId = [];
        $binLocationsById = [];
        foreach ($importElements->getElements() as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];

            $errors = $this->validateRowSchema($normalizedRow, $normalizedToOriginalColumnNameMapping);
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            $productId = $productNumberIdMapping[mb_strtolower($normalizedRow['productNumber'])] ?? null;
            if (!$productId) {
                $errors->addError(StockImportException::createProductNotFoundError($normalizedRow['productNumber']));
            }
            $stockImportLocation = $this->stockImportLocationFinder->findStockImportLocation([
                'binLocationCode' => $normalizedRow['binLocationCode'] ?? null,
                'warehouseCode' => $normalizedRow['warehouseCode'] ?? null,
                'warehouseName' => $normalizedRow['warehouseName'] ?? null,
            ], $context);
            if (!$stockImportLocation) {
                $errors->addError(StockImportException::createStockLocationNotFoundError());
            }

            $hasStockValue = isset($normalizedRow['stock']) && $normalizedRow['stock'] !== '';
            $hasChangeValue = isset($normalizedRow['change']) && $normalizedRow['change'] !== '';

            if ($hasStockValue && is_numeric($normalizedRow['stock'])) {
                $stock = (int) $normalizedRow['stock'];

                if ($stock > 1_000_000_000) {
                    $errors->addError(StockImportException::createQuantityExceedsMaximumError());
                }
            }

            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            try {
                if ($hasStockValue || $hasChangeValue) {
                    $this->updateStock(
                        $import,
                        $importElement,
                        $normalizedRow,
                        $stockImportLocation,
                        $productId,
                        $errors,
                        $context,
                    );
                }

                if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                    continue;
                }

                $this->stockLimitConfigurationUpdater->upsertStockLimitConfiguration(
                    $normalizedRow,
                    $productId,
                    $stockImportLocation,
                    $context,
                );

                $hasDefaultBinLocationValue = isset($normalizedRow['defaultBinLocation'])
                    && $normalizedRow['defaultBinLocation'] !== '';
                if ($hasDefaultBinLocationValue) {
                    $this->updateDefaultBinLocation(
                        $importElement,
                        $normalizedRow,
                        $stockImportLocation,
                        $productId,
                        $binLocationsById,
                        $productWarehouseConfigurationsByConcatenatedProductAndWarehouseId,
                        $errors,
                        $context,
                    );
                }
            } catch (Throwable $exception) {
                throw ImportException::rowImportError($exception, $importElement->getRowNumber());
            }
        }

        $nextRowNumberToRead += $this->batchSize;

        return $nextRowNumberToRead;
    }

    private function updateStock(
        ImportExportEntity $import,
        ImportExportElementEntity $importElement,
        array $normalizedRow,
        StockImportLocation $stockImportLocation,
        string $productId,
        JsonApiErrors $errors,
        Context $context,
    ): void {
        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($stockImportLocation, $errors, $context, $import, $normalizedRow, $importElement, $productId): void {
                    $stockChange = $this->stockChangeCalculator->calculateStockChange(
                        $normalizedRow,
                        $productId,
                        $stockImportLocation,
                        $errors,
                        $context,
                    );

                    if ($this->failOnErrors($importElement->getId(), $errors, $context) || $stockChange === 0) {
                        return;
                    }

                    switch ($stockImportLocation->getStockImportLocationType()) {
                        case StockImportLocationType::StockArea:
                            $stockArea = $stockImportLocation->getStockArea();
                            $this->lockProductStocks(
                                $productId,
                                $context,
                            );
                            if ($stockChange > 0) {
                                $stockingRequest = new StockingRequest(
                                    productQuantities: ProductQuantityImmutableCollection::create([new ProductQuantity($productId, $stockChange)]),
                                    stockArea: $stockArea,
                                );
                                $productQuantityLocations = $this->stockingStrategy->calculateStockingSolution(
                                    $stockingRequest,
                                    $context,
                                );
                                $stockMovements = $productQuantityLocations->createStockMovementsWithSource(
                                    StockLocationReference::import(),
                                    [
                                        'userId' => $import->getUserId(),
                                    ],
                                );
                                $this->stockMovementService->moveStock($stockMovements, $context);
                            } else {
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
                                            sourceStockArea: $stockArea,
                                        ),
                                        context: $context,
                                    );
                                    $stockMovements = $pickingSolution->createStockMovementsWithDestination(
                                        StockLocationReference::import(),
                                        [
                                            'userId' => $import->getUserId(),
                                        ],
                                    );
                                    $this->stockMovementService->moveStock($stockMovements, $context);
                                } catch (PickingStrategyStockShortageException $e) {
                                    $errors->addError($e->getJsonApiError());
                                }
                            }
                            break;
                        case StockImportLocationType::StockLocationInWarehouse:
                            $stockMovement = StockMovement::create([
                                'productId' => $productId,
                                'source' => StockLocationReference::import(),
                                'destination' => $stockImportLocation->getStockLocationReference(),
                                'quantity' => $stockChange,
                                'userId' => $import->getUserId(),
                            ]);

                            $this->stockMovementService->moveStock([$stockMovement], $context);
                            break;
                    }
                },
            );
        } catch (StockMovementServiceValidationException $e) {
            $errors->addError($e->serializeToJsonApiError());
        }
    }

    private function updateDefaultBinLocation(
        ImportExportElementEntity $importElement,
        array $normalizedRow,
        StockImportLocation $stockImportLocation,
        string $productId,
        array &$binLocationsById,
        array &$productWarehouseConfigurationsByConcatenatedProductAndWarehouseId,
        JsonApiErrors $errors,
        Context $context,
    ): void {
        if ($stockImportLocation->getStockImportLocationType() === StockImportLocationType::StockArea) {
            return;
        }

        /** @var StockLocationReference $stockLocationReference */
        $stockLocationReference = $stockImportLocation->getStockLocationReference();
        if ($normalizedRow['defaultBinLocation'] && $stockLocationReference->getLocationTypeTechnicalName() !== LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION) {
            $errors->addError(StockImportException::createBinLocationOrWarehouseForDefaultBinLocationMissing());
            $this->failOnErrors($importElement->getId(), $errors, $context);

            return;
        }

        if ($stockLocationReference->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION) {
            $concatenatedProductAndWarehouseId = $productId . $stockLocationReference->getPrimaryKey();
            /** @var ?BinLocationEntity $binLocation */
            $binLocation = $binLocationsById[$stockLocationReference->getPrimaryKey()] ?? null;
            /** @var ProductWarehouseConfigurationEntity $configuration */
            $configuration = $productWarehouseConfigurationsByConcatenatedProductAndWarehouseId[$concatenatedProductAndWarehouseId] ?? null;

            if (!$binLocation) {
                /** @var BinLocationEntity $binLocation */
                $binLocation = $this->entityManager->findByPrimaryKey(
                    BinLocationDefinition::class,
                    $stockLocationReference->getPrimaryKey(),
                    $context,
                );
                $binLocationsById[$stockLocationReference->getPrimaryKey()] = $binLocation;
            }

            if (!$configuration) {
                /** @var ?ProductWarehouseConfigurationEntity $configuration */
                $configuration = $this->entityManager->findOneBy(
                    ProductWarehouseConfigurationDefinition::class,
                    [
                        'productId' => $productId,
                        'warehouseId' => $binLocation->getWarehouseId(),
                    ],
                    $context,
                );
                $productWarehouseConfigurationsByConcatenatedProductAndWarehouseId[$concatenatedProductAndWarehouseId] = $configuration;
            }

            $payload = null;
            if ($normalizedRow['defaultBinLocation']) {
                // Upsert product warehouse configuration with given default bin location
                $payload = [
                    'id' => $configuration ? $configuration->getId() : Uuid::randomHex(),
                    'productId' => $productId,
                    'warehouseId' => $binLocation->getWarehouseId(),
                    'defaultBinLocationId' => $binLocation->getId(),
                ];
                if (!$configuration) {
                    // Set default value for reorderPoint when creating a new configuration
                    $payload['reorderPoint'] = 0;
                }
            } elseif ($configuration && $configuration->getDefaultBinLocationId() === $binLocation->getId()) {
                // Remove current default bin location from the product warehouse configuration
                $payload = [
                    'id' => $configuration->getId(),
                    'defaultBinLocationId' => null,
                ];
            }

            if ($payload) {
                $this->entityManager->upsert(
                    ProductWarehouseConfigurationDefinition::class,
                    [$payload],
                    $context,
                );
            }
        }
    }

    private function getProductNumberIdMapping(array $normalizedRows, Context $context): array
    {
        $productNumbers = array_column($normalizedRows, 'productNumber');
        /** @var ProductCollection $products */
        $products = $this->entityManager->findBy(ProductDefinition::class, [
            'productNumber' => $productNumbers,
        ], $context);

        $productNumbers = $products->map(fn(ProductEntity $product) => mb_strtolower($product->getProductNumber()));

        return array_combine($productNumbers, $products->getKeys());
    }

    private function validateRowSchema(array $normalizedRow, array $normalizedToOriginalColumnNameMapping): JsonApiErrors
    {
        $errors = $this->validator->validateRow($normalizedRow, $normalizedToOriginalColumnNameMapping);

        if (
            isset($normalizedRow['binLocationCode'])
            && !isset($normalizedRow['warehouseCode'])
            && !isset($normalizedRow['warehouseName'])
        ) {
            $errors->addError(StockImportException::createWarehouseForBinLocationMissing());
        }

        return $errors;
    }

    private function lockProductStocks(string $productId, Context $context): void
    {
        $this->entityManager->lockPessimistically(StockDefinition::class, ['productId' => $productId], $context);
    }

    private function failOnErrors(string $importElementId, JsonApiErrors $errors, Context $context): bool
    {
        if (count($errors) > 0) {
            $this->importExportStateService->failImportExportElement($importElementId, $errors, $context);

            return true;
        }

        return false;
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return JsonApiErrors::noError();
    }
}
