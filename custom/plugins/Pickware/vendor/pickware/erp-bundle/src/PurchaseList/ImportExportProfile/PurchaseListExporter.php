<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\ImportExportProfile;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\Model\DemandPlanningListItemEntity;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemEntity;
use Pickware\PickwareErpStarter\Supplier\MultipleSuppliersPerProductProductionFeatureFlag;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'purchase-list'])]
class PurchaseListExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'purchase-list';
    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_PRODUCT_GTIN = 'product.ean';
    public const COLUMN_MANUFACTURER_NUMBER = 'product.manufacturerNumber';
    public const COLUMN_MANUFACTURER_NAME = 'product.manufacturer.name';
    public const COLUMN_SUPPLIER_PRODUCT_NUMBER = 'productSupplierConfiguration.supplierProductNumber';
    public const COLUMN_SUPPLIER_NAME = 'productSupplierConfiguration.supplier.name';
    public const COLUMN_PHYSICAL_STOCK = 'product.extensions.pickwareErpPickwareProduct.physicalStock';
    public const COLUMN_RESERVED_STOCK = 'product.extensions.pickwareErpPickwareProduct.reservedStock';
    public const COLUMN_AVAILABLE_STOCK = 'product.stock';
    public const COLUMN_REORDER_POINT = 'product.extensions.pickwareErpPickwareProduct.reorderPoint';
    public const COLUMN_SALES = 'sales';
    public const COLUMN_SALES_PREDICTION = 'salesPrediction';
    public const COLUMN_INCOMING_STOCK = 'product.extensions.pickwareErpPickwareProduct.incomingStock';
    public const COLUMN_MIN_PURCHASE = 'productSupplierConfiguration.minPurchase';
    public const COLUMN_PURCHASE_STEPS = 'productSupplierConfiguration.purchaseSteps';
    public const COLUMN_PURCHASE_SUGGESTION = 'purchaseSuggestion';
    public const COLUMN_DELIVERY_TIME = 'deliveryTime';
    public const COLUMN_QUANTITY = 'quantity';
    public const COLUMN_PURCHASE_PRICE_NET = 'purchasePriceNet';
    public const COLUMN_PURCHASE_TOTAL_NET = 'purchaseTotalNet';
    public const COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_PRODUCT_GTIN,
        self::COLUMN_MANUFACTURER_NUMBER,
        self::COLUMN_MANUFACTURER_NAME,
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_SUPPLIER_NAME,
        self::COLUMN_PHYSICAL_STOCK,
        self::COLUMN_RESERVED_STOCK,
        self::COLUMN_AVAILABLE_STOCK,
        self::COLUMN_REORDER_POINT,
        self::COLUMN_INCOMING_STOCK,
        self::COLUMN_MIN_PURCHASE,
        self::COLUMN_PURCHASE_STEPS,
        self::COLUMN_PURCHASE_SUGGESTION,
        self::COLUMN_DELIVERY_TIME,
        self::COLUMN_QUANTITY,
        self::COLUMN_PURCHASE_PRICE_NET,
        self::COLUMN_PURCHASE_TOTAL_NET,
        self::COLUMN_SALES,
        self::COLUMN_SALES_PREDICTION,
    ];
    public const DEFAULT_COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_SUPPLIER_NAME,
        self::COLUMN_PHYSICAL_STOCK,
        self::COLUMN_RESERVED_STOCK,
        self::COLUMN_AVAILABLE_STOCK,
        self::COLUMN_REORDER_POINT,
        self::COLUMN_INCOMING_STOCK,
        self::COLUMN_MIN_PURCHASE,
        self::COLUMN_PURCHASE_STEPS,
        self::COLUMN_PURCHASE_SUGGESTION,
        self::COLUMN_QUANTITY,
        self::COLUMN_PURCHASE_PRICE_NET,
        self::COLUMN_PURCHASE_TOTAL_NET,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.purchase-list-export.columns.product-number',
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.purchase-list-export.columns.product-name',
        self::COLUMN_PRODUCT_GTIN => 'pickware-erp-starter.purchase-list-export.columns.gtin',
        self::COLUMN_MANUFACTURER_NUMBER => 'pickware-erp-starter.purchase-list-export.columns.manufacturer-number',
        self::COLUMN_MANUFACTURER_NAME => 'pickware-erp-starter.purchase-list-export.columns.manufacturer',
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER => 'pickware-erp-starter.purchase-list-export.columns.supplier-product-number',
        self::COLUMN_SUPPLIER_NAME => 'pickware-erp-starter.purchase-list-export.columns.supplier-name',
        self::COLUMN_PHYSICAL_STOCK => 'pickware-erp-starter.purchase-list-export.columns.stock',
        self::COLUMN_RESERVED_STOCK => 'pickware-erp-starter.purchase-list-export.columns.reserved-stock',
        self::COLUMN_AVAILABLE_STOCK => 'pickware-erp-starter.purchase-list-export.columns.available-stock',
        self::COLUMN_REORDER_POINT => 'pickware-erp-starter.purchase-list-export.columns.reorder-point',
        self::COLUMN_INCOMING_STOCK => 'pickware-erp-starter.purchase-list-export.columns.incoming-stock',
        self::COLUMN_MIN_PURCHASE => 'pickware-erp-starter.purchase-list-export.columns.min-purchase',
        self::COLUMN_PURCHASE_STEPS => 'pickware-erp-starter.purchase-list-export.columns.purchase-steps',
        self::COLUMN_PURCHASE_SUGGESTION => 'pickware-erp-starter.purchase-list-export.columns.purchase-suggestion',
        self::COLUMN_DELIVERY_TIME => 'pickware-erp-starter.purchase-list-export.columns.delivery-time',
        self::COLUMN_QUANTITY => 'pickware-erp-starter.purchase-list-export.columns.quantity',
        self::COLUMN_PURCHASE_PRICE_NET => 'pickware-erp-starter.purchase-list-export.columns.purchase-price-net',
        self::COLUMN_PURCHASE_TOTAL_NET => 'pickware-erp-starter.purchase-list-export.columns.purchase-total-net',
        self::COLUMN_SALES => 'pickware-erp-starter.purchase-list-export.columns.sales',
        self::COLUMN_SALES_PREDICTION => 'pickware-erp-starter.purchase-list-export.columns.sales-prediction',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly Translator $translator,
        private readonly ProductNameFormatterService $productNameFormatterService,
        #[Autowire('%pickware_erp.import_export.profiles.purchase-list.batch_size%')]
        private readonly int $batchSize,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();
        $columns = $this->getExportColumns($export);

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);

        $exportRows = $this->getPurchaseListExportRows(
            $criteria,
            $exportConfig['locale'],
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
        return PurchaseListItemDefinition::class;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.purchase-list-export.file-name'),
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
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

        $headerTranslations = $this->getCsvHeaderTranslations($export->getConfig()['locale'], $context);
        $columns = $this->getExportColumns($export);

        $translatedColumns = array_map(
            fn(string $column) => $headerTranslations[$column],
            $columns,
        );

        return [$translatedColumns];
    }

    /**
     * @return array<string>
     */
    private function getExportColumns(ImportExportEntity $export): array
    {
        $defaultColumns = self::DEFAULT_COLUMNS;

        if ($this->featureFlagService->isActive(MultipleSuppliersPerProductProductionFeatureFlag::NAME)) {
            $defaultColumns[] = self::COLUMN_DELIVERY_TIME;
        }

        return $export->getConfig()['columns'] ?? $defaultColumns;
    }

    /**
     * @param array<string> $columns
     * @return array<array<string, mixed>>
     */
    private function getPurchaseListExportRows(
        Criteria $criteria,
        string $locale,
        array $columns,
        Context $context,
    ): array {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);
        $criteria = EntityManager::sanitizeCriteria($criteria);

        $criteria->addAssociations([
            'product',
            'product.manufacturer',
            'product.extensions.pickwareErpPickwareProduct',
            'product.extensions.pickwareErpDemandPlanningListItems.reportConfig.aggregationSession',
            'productSupplierConfiguration',
            'productSupplierConfiguration.supplier',
            'productSupplierConfiguration.purchasePrices',
        ]);

        /** @var EntityCollection<PurchaseListItemEntity> $purchaseListItems */
        $purchaseListItems = $this->entityManager->findBy(PurchaseListItemDefinition::class, $criteria, $context);

        $productIds = array_unique($purchaseListItems->map(
            fn(PurchaseListItemEntity $purchaseListItem) => $purchaseListItem->getProductId(),
        ));
        $productNames = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $context);

        $rows = [];
        foreach ($purchaseListItems as $purchaseListItem) {
            $product = $purchaseListItem->getProduct();
            $productSupplierConfiguration = $purchaseListItem->getProductSupplierConfiguration();

            $supplier = $productSupplierConfiguration?->getSupplier();
            /** @var PickwareProductEntity|null $pickwareProduct */
            $pickwareProduct = $product->getExtension('pickwareErpPickwareProduct');
            /** @var EntityCollection<DemandPlanningListItemEntity> $pickwareDemandPlanningListItems */
            $pickwareDemandPlanningListItems = $product->getExtension('pickwareErpDemandPlanningListItems');
            $currentUserDemanPlanningListItems = $pickwareDemandPlanningListItems->filter(
                fn(DemandPlanningListItemEntity $demandPlanningListItemEntity) => $demandPlanningListItemEntity->getReportConfig()?->getAggregationSession()->getUserId() === ContextExtension::getUserId($context),
            );

            $purchasePrices = $productSupplierConfiguration?->getPurchasePrices();
            $purchasePrice = $purchasePrices->first();

            $priceNet = $purchasePrice?->getNet() ?? '';
            $columnValues = [
                self::COLUMN_PRODUCT_NUMBER => $product->getProductNumber(),
                self::COLUMN_PRODUCT_NAME => $productNames[$product->getId()],
                self::COLUMN_PRODUCT_GTIN => $product->getEan() ?? '',
                self::COLUMN_MANUFACTURER_NUMBER => $product->getManufacturerNumber() ?? '',
                self::COLUMN_MANUFACTURER_NAME => $product->getManufacturer()?->getName() ?? '',
                self::COLUMN_SUPPLIER_PRODUCT_NUMBER => $productSupplierConfiguration?->getSupplierProductNumber() ?? '',
                self::COLUMN_SUPPLIER_NAME => $supplier->getName(),
                self::COLUMN_PHYSICAL_STOCK => $pickwareProduct?->getPhysicalStock() ?? '',
                self::COLUMN_RESERVED_STOCK => $pickwareProduct?->getReservedStock() ?? '',
                self::COLUMN_AVAILABLE_STOCK => $product->getStock(),
                self::COLUMN_REORDER_POINT => $pickwareProduct?->getReorderPoint() ?? '',
                self::COLUMN_INCOMING_STOCK => $pickwareProduct?->getIncomingStock() ?? '',
                self::COLUMN_MIN_PURCHASE => $productSupplierConfiguration?->getMinPurchase() ?? '',
                self::COLUMN_PURCHASE_STEPS => $productSupplierConfiguration?->getPurchaseSteps() ?? '',
                self::COLUMN_PURCHASE_SUGGESTION => $purchaseListItem->getPurchaseSuggestion() ?? '',
                self::COLUMN_DELIVERY_TIME => $productSupplierConfiguration?->getDeliveryTimeDays() ?? $supplier->getDefaultDeliveryTime() ?? '',
                self::COLUMN_QUANTITY => $purchaseListItem->getQuantity(),
                self::COLUMN_PURCHASE_PRICE_NET => $priceNet,
                self::COLUMN_PURCHASE_TOTAL_NET => $priceNet !== '' ? $purchaseListItem->getQuantity() * $priceNet : '',
                self::COLUMN_SALES => $currentUserDemanPlanningListItems->first()?->getSales() ?? '',
                self::COLUMN_SALES_PREDICTION => $currentUserDemanPlanningListItems->first()?->getSalesPrediction() ?? '',
            ];

            $currentRow = [];
            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]] = $columnValues[$column];
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(fn($snippedId) => $this->translator->translate($snippedId), self::COLUMN_TRANSLATIONS);
    }
}
