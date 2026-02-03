<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerStockLocation;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationMappingDefinition;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationMappingEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationCollection;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'stock-per-stock-location'])]
class StockPerStockLocationExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'stock-per-stock-location';
    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_WAREHOUSE_NAME = 'warehouse.name';
    public const COLUMN_WAREHOUSE_CODE = 'warehouse.code';
    public const COLUMN_BIN_LOCATION_CODE = 'binLocation.code';
    public const COLUMN_CHANGE = 'change';
    public const COLUMN_OLD_STOCK = 'quantity';
    public const COLUMN_STOCK = 'stock.quantity';
    public const COLUMN_DEFAULT_BIN_LOCATION = 'defaultBinLocation';
    public const COLUMN_REORDER_POINT = 'productStockLocationConfiguration.reorderPoint';
    public const COLUMN_MAXIMUM_QUANTITY = 'productStockLocationConfiguration.targetMaximumQuantity';
    public const COLUMN_REPLENISHMENT_QUANTITY = 'replenishmentQuantity';
    public const COLUMN_BATCH = 'batch';
    public const COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_WAREHOUSE_NAME,
        self::COLUMN_WAREHOUSE_CODE,
        self::COLUMN_BIN_LOCATION_CODE,
        self::COLUMN_DEFAULT_BIN_LOCATION,
        self::COLUMN_MAXIMUM_QUANTITY,
        self::COLUMN_REPLENISHMENT_QUANTITY,
        self::COLUMN_REORDER_POINT,
        self::COLUMN_CHANGE,
        self::COLUMN_STOCK,
        self::COLUMN_OLD_STOCK,
        self::COLUMN_BATCH,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.stock-export.columns.product-name',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.stock-export.columns.product-number',
        self::COLUMN_WAREHOUSE_NAME => 'pickware-erp-starter.stock-export.columns.warehouse-name',
        self::COLUMN_WAREHOUSE_CODE => 'pickware-erp-starter.stock-export.columns.warehouse-code',
        self::COLUMN_BIN_LOCATION_CODE => 'pickware-erp-starter.stock-export.columns.bin-location',
        self::COLUMN_STOCK => 'pickware-erp-starter.stock-export.columns.stock',
        self::COLUMN_OLD_STOCK => 'pickware-erp-starter.stock-export.columns.stock',
        self::COLUMN_CHANGE => 'pickware-erp-starter.stock-export.columns.change',
        self::COLUMN_DEFAULT_BIN_LOCATION => 'pickware-erp-starter.stock-export.columns.default-bin-location',
        self::COLUMN_REORDER_POINT => 'pickware-erp-starter.stock-export.columns.reorder-point',
        self::COLUMN_MAXIMUM_QUANTITY => 'pickware-erp-starter.stock-export.columns.target-maximum-quantity',
        self::COLUMN_REPLENISHMENT_QUANTITY => 'pickware-erp-starter.stock-export.columns.replenishment-quantity',
    ];

    private EntityManager $entityManager;
    private int $batchSize;
    private Translator $translator;
    private ProductNameFormatterService $productNameFormatterService;
    private CriteriaJsonSerializer $criteriaJsonSerializer;

    public function __construct(
        EntityManager $entityManager,
        CriteriaJsonSerializer $criteriaJsonSerializer,
        Translator $translator,
        ProductNameFormatterService $productNameFormatterService,
        #[Autowire('%pickware_erp.import_export.profiles.stock_per_stock_location.batch_size%')]
        int $batchSize,
    ) {
        $this->entityManager = $entityManager;
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
        $this->batchSize = $batchSize;
        $this->translator = $translator;
        $this->productNameFormatterService = $productNameFormatterService;
    }

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();
        $columns = $this->getColumnsForExport($exportConfig);

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);

        $exportRows = $this->getStockOverviewPerStockLocationExportRows(
            $criteria,
            $exportConfig['locale'],
            $exportConfig['exportStockValues'],
            $columns,
            $context,
        );

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $exportRow) {
            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $nextRowNumberToWrite + $index,
                'rowData' => $exportRow,
            ];
        }
        $this->entityManager->create(
            ImportExportElementDefinition::class,
            $exportElementPayloads,
            $context,
        );

        $nextRowNumberToWrite += $this->batchSize;

        if (count($exportRows) < $this->batchSize) {
            return null;
        }

        return $nextRowNumberToWrite;
    }

    public function getEntityDefinitionClassName(): string
    {
        return ProductStockLocationMappingDefinition::class;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.stock-export.file-name'),
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        $columns = $config['columns'] ?? [];

        $invalidColumns = array_diff($columns, self::COLUMNS);
        foreach ($invalidColumns as $invalidColumn) {
            $errors->addError(CsvErrorFactory::invalidColumn($invalidColumn));
        }

        return $errors;
    }

    public function getHeader(string $exportId, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $config = $export->getConfig();

        $columns = $this->getColumnsForExport($config);
        if ($config['exportStockValues']) {
            if (!in_array(self::COLUMN_STOCK, $columns, true) && !in_array(self::COLUMN_OLD_STOCK, $columns, true)) {
                $columns[] = self::COLUMN_STOCK;
            }
        } else {
            $columns[] = self::COLUMN_CHANGE;
        }

        $headerTranslations = $this->getCsvHeaderTranslations($config['locale'], $context);
        $translatedColumns = array_map(
            fn(string $column) => $headerTranslations[$column],
            array_unique($columns),
        );

        return [$translatedColumns];
    }

    /**
     * @param Criteria $criteria Only filters, sorting, limit and offset are respected
     */
    public function getStockOverviewPerStockLocationExportRows(
        Criteria $criteria,
        string $locale,
        bool $exportStockValues,
        array $columns,
        Context $context,
    ): array {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);

        $mappings = $context->enableInheritance(fn(Context $inheritanceContext) => $this->entityManager->findBy(
            ProductStockLocationMappingDefinition::class,
            EntityManager::sanitizeCriteria($criteria),
            $inheritanceContext,
            [
                'stock',
                'productStockLocationConfiguration',
                'product.options',
                'product.pickwareErpProductWarehouseConfigurations',
                'warehouse',
                'binLocation',
                'binLocation.warehouse',
            ],
        ));

        $productIds = $mappings->map(fn(ProductStockLocationMappingEntity $mapping) => $mapping->getProductId());
        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            $productIds,
            [],
            $context,
        );

        $defaultBinLocationBooleanTranslations = [
            true => $this->translator->translate('pickware-erp-starter.stock-export.default-bin-location.true'),
            false => $this->translator->translate('pickware-erp-starter.stock-export.default-bin-location.false'),
        ];

        $rows = [];
        /** @var ProductStockLocationMappingEntity $mapping */
        foreach ($mappings as $mapping) {
            $stock = $mapping->getStock();
            $warehouse = $mapping->getWarehouse();
            if (!$warehouse && $mapping->getBinLocation()) {
                $warehouse = $mapping->getBinLocation()->getWarehouse();
            }
            $columnValues = [
                self::COLUMN_PRODUCT_NAME => $productNames[$mapping->getProduct()->getId()],
                self::COLUMN_PRODUCT_NUMBER => $mapping->getProduct()->getProductNumber(),
                self::COLUMN_WAREHOUSE_NAME => $warehouse ? $warehouse->getName() : '',
                self::COLUMN_WAREHOUSE_CODE => $warehouse ? $warehouse->getCode() : '',
                self::COLUMN_BIN_LOCATION_CODE => $this->getBinLocationLabel($mapping->getBinLocation()),
                self::COLUMN_MAXIMUM_QUANTITY => $mapping->getProductStockLocationConfiguration()?->getTargetMaximumQuantity() ?? '',
                self::COLUMN_REORDER_POINT => $mapping->getProductStockLocationConfiguration()?->getReorderPoint() ?? '',
                self::COLUMN_REPLENISHMENT_QUANTITY => $this->getReplenishmentQuantity($mapping),
                self::COLUMN_DEFAULT_BIN_LOCATION => $defaultBinLocationBooleanTranslations[(int) $this->isOnDefaultBinLocation($stock, $mapping->getProduct())],
                self::COLUMN_STOCK => $stock?->getQuantity() ?? 0,
                self::COLUMN_OLD_STOCK => $stock?->getQuantity() ?? 0,
                self::COLUMN_CHANGE => 0,
            ];

            if ($exportStockValues) {
                if (!in_array(self::COLUMN_STOCK, $columns, true) && !in_array(self::COLUMN_OLD_STOCK, $columns, true)) {
                    $columns[] = self::COLUMN_STOCK;
                }
            } elseif (!in_array(self::COLUMN_CHANGE, $columns, true)) {
                $columns[] = self::COLUMN_CHANGE;
            }

            $currentRow = [];
            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]] = $columnValues[$column];
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }

    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(fn($snippedId) => $this->translator->translate($snippedId), self::COLUMN_TRANSLATIONS);
    }

    private function getBinLocationLabel(?BinLocationEntity $binLocation): string
    {
        if ($binLocation) {
            return $binLocation->getCode();
        }

        return $this->translator->translate('pickware-erp-starter.stock-export.unknown-stock-location');
    }

    private function isOnDefaultBinLocation(?StockEntity $stock, ProductEntity $productEntity): bool
    {
        if ($stock === null) {
            return false;
        }
        /** @var ProductWarehouseConfigurationCollection $configurations */
        $configurations = $productEntity->getExtension('pickwareErpProductWarehouseConfigurations');
        if ($configurations && $configurations->count() !== 0) {
            foreach ($configurations->getElements() as $configuration) {
                if (
                    $configuration->getDefaultBinLocationId()
                    && $stock->getProductId() === $configuration->getProductId()
                    && $stock->getBinLocationId() === $configuration->getDefaultBinLocationId()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getReplenishmentQuantity(ProductStockLocationMappingEntity $mapping): int
    {
        $configuration = $mapping->getProductStockLocationConfiguration();
        $targetStock = $configuration?->getTargetMaximumQuantity() ?? $configuration?->getReorderPoint();
        if ($targetStock === null) {
            return 0;
        }

        return max(0, $targetStock - ($mapping->getStock()?->getQuantity() ?? 0));
    }

    /**
     * @param array<string, mixed> $config
     * @return string[]
     */
    private function getColumnsForExport(array $config): array
    {
        $columns = $config['columns'] ?? [
            self::COLUMN_PRODUCT_NAME,
            self::COLUMN_PRODUCT_NUMBER,
            self::COLUMN_WAREHOUSE_NAME,
            self::COLUMN_WAREHOUSE_CODE,
            self::COLUMN_BIN_LOCATION_CODE,
            self::COLUMN_DEFAULT_BIN_LOCATION,
            $config['exportStockValues'] ? self::COLUMN_STOCK : self::COLUMN_CHANGE,
            self::COLUMN_REORDER_POINT,
            self::COLUMN_MAXIMUM_QUANTITY,
            self::COLUMN_REPLENISHMENT_QUANTITY,
        ];

        $binLocationIndex = array_search(self::COLUMN_BIN_LOCATION_CODE, $columns, true);
        // Add the default bin location column if the bin location code is exported
        if (
            $binLocationIndex !== false
            && !in_array(self::COLUMN_DEFAULT_BIN_LOCATION, $columns, true)
        ) {
            array_splice($columns, $binLocationIndex + 1, 0, [self::COLUMN_DEFAULT_BIN_LOCATION]);
        }

        $columnsToExclude = $config['exportStockValues'] ? [self::COLUMN_CHANGE] : [self::COLUMN_STOCK];
        $columnsToExclude[] = self::COLUMN_BATCH;

        return array_diff($columns, $columnsToExclude);
    }
}
