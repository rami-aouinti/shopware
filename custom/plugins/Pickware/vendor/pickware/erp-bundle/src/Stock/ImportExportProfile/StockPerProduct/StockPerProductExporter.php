<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerProduct;

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
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'stock-per-product'])]
class StockPerProductExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'stock-per-product';
    public const COLUMN_PRODUCT_NUMBER = 'productNumber';
    public const COLUMN_PRODUCT_NAME = 'name';
    public const COLUMN_REORDER_POINT = 'extensions.pickwareErpPickwareProduct.reorderPoint';
    public const COLUMN_MAXIMUM_QUANTITY = 'extensions.pickwareErpPickwareProduct.targetMaximumQuantity';
    public const COLUMN_REPLENISHMENT_QUANTITY = 'replenishmentQuantity';
    public const COLUMN_RESERVED_STOCK = 'extensions.pickwareErpPickwareProduct.reservedStock';
    public const COLUMN_AVAILABLE_STOCK = 'availableStock';
    public const COLUMN_CHANGE = 'change';
    public const COLUMN_STOCK = 'extensions.pickwareErpPickwareProduct.physicalStock';
    public const COLUMN_BATCH = 'batch';
    public const COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_REORDER_POINT,
        self::COLUMN_MAXIMUM_QUANTITY,
        self::COLUMN_REPLENISHMENT_QUANTITY,
        self::COLUMN_RESERVED_STOCK,
        self::COLUMN_AVAILABLE_STOCK,
        self::COLUMN_CHANGE,
        self::COLUMN_STOCK,
        self::COLUMN_BATCH,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.stock-export.columns.product-name',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.stock-export.columns.product-number',
        self::COLUMN_REORDER_POINT => 'pickware-erp-starter.stock-export.columns.reorder-point',
        self::COLUMN_MAXIMUM_QUANTITY => 'pickware-erp-starter.stock-export.columns.target-maximum-quantity',
        self::COLUMN_REPLENISHMENT_QUANTITY => 'pickware-erp-starter.stock-export.columns.replenishment-quantity',
        self::COLUMN_RESERVED_STOCK => 'pickware-erp-starter.stock-export.columns.reserved-stock',
        self::COLUMN_AVAILABLE_STOCK => 'pickware-erp-starter.stock-export.columns.available-stock',
        self::COLUMN_STOCK => 'pickware-erp-starter.stock-export.columns.stock',
        self::COLUMN_CHANGE => 'pickware-erp-starter.stock-export.columns.change',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly Translator $translator,
        private readonly ProductNameFormatterService $productNameFormatterService,
        #[Autowire('%pickware_erp.import_export.profiles.stock_per_product.batch_size%')]
        private readonly int $batchSize,
    ) {}

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

        $exportRows = $this->getStockOverviewPerProductExportRows(
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
        return ProductDefinition::class;
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
        $columns[] = $config['exportStockValues'] ? self::COLUMN_STOCK : self::COLUMN_CHANGE;

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
    private function getStockOverviewPerProductExportRows(
        Criteria $criteria,
        string $locale,
        bool $exportStockValues,
        array $columns,
        Context $context,
    ): array {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);
        $criteria = EntityManager::sanitizeCriteria($criteria);

        $productNames = [];
        $products = $context->enableInheritance(function(Context $inheritanceContext) use ($criteria, &$productNames) {
            $criteria->addAssociations([
                'options',
                'pickwareErpPickwareProduct',
            ]);

            // Fetch ids to format names before fetching the full products to reduce memory usage peak
            $productIds = $this->entityManager->findIdsBy(ProductDefinition::class, $criteria, $inheritanceContext);
            $productNames = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $inheritanceContext);

            // Do not re-use the same criteria to avoid concurrency issues when fetching products multiple times. Use
            // the id result from the first fetch instead.
            $productFetchCriteria = (clone $criteria)
                ->resetFilters()
                ->addFilter(new EqualsAnyFilter('id', $productIds));

            return $this->entityManager->findBy(ProductDefinition::class, $productFetchCriteria, $inheritanceContext);
        });

        $rows = [];
        /** @var ProductEntity $product */
        foreach ($products as $product) {
            /** @var PickwareProductEntity $pickwareProduct */
            $pickwareProduct = $product->getExtension('pickwareErpPickwareProduct');
            $columnValues = [
                self::COLUMN_PRODUCT_NAME => $productNames[$product->getId()],
                self::COLUMN_PRODUCT_NUMBER => $product->getProductNumber(),
                self::COLUMN_REORDER_POINT => $pickwareProduct->getReorderPoint(),
                self::COLUMN_MAXIMUM_QUANTITY => $pickwareProduct->getTargetMaximumQuantity() ?? '',
                self::COLUMN_REPLENISHMENT_QUANTITY => $this->getReplenishmentQuantity($pickwareProduct),
                self::COLUMN_RESERVED_STOCK => $pickwareProduct->getReservedStock(),
                self::COLUMN_AVAILABLE_STOCK => $product->getAvailableStock(),
                self::COLUMN_STOCK => $pickwareProduct->getPhysicalStock(),
                self::COLUMN_CHANGE => 0,
            ];

            if ($exportStockValues) {
                if (!in_array(self::COLUMN_STOCK, $columns, true)) {
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

    private function getReplenishmentQuantity(PickwareProductEntity $pickwareProduct): int
    {
        $targetStock = $pickwareProduct->getTargetMaximumQuantity() ?? $pickwareProduct->getReorderPoint();

        return max(0, $targetStock - $pickwareProduct->getPhysicalStock());
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
            $config['exportStockValues'] ? self::COLUMN_STOCK : self::COLUMN_CHANGE,
            self::COLUMN_REORDER_POINT,
            self::COLUMN_MAXIMUM_QUANTITY,
            self::COLUMN_REPLENISHMENT_QUANTITY,
        ];

        $columnsToExclude = $config['exportStockValues'] ? [self::COLUMN_CHANGE] : [self::COLUMN_STOCK];
        $columnsToExclude[] = self::COLUMN_BATCH;

        return array_diff($columns, $columnsToExclude);
    }
}
