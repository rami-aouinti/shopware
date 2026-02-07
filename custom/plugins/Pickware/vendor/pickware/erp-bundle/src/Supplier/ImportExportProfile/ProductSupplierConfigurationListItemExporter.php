<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\ImportExportProfile;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationListItemCollection;
use Pickware\PickwareErpStarter\Supplier\MultipleSuppliersPerProductProductionFeatureFlag;
use Pickware\PickwareErpStarter\Supplier\ProductSupplierConfigurationListItemService;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'product-supplier-configuration-list-item'])]
class ProductSupplierConfigurationListItemExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'product-supplier-configuration-list-item';
    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_GTIN = 'product.ean';
    public const COLUMN_MANUFACTURER_NAME = 'product.manufacturer.name';
    public const COLUMN_MANUFACTURER_NUMBER = 'product.manufacturerNumber';
    public const COLUMN_SUPPLIER_NAME = 'productSupplierConfiguration.supplier.name';
    public const COLUMN_SUPPLIER_NUMBER = 'productSupplierConfiguration.supplier.number';
    public const COLUMN_SUPPLIER_PRODUCT_NUMBER = 'productSupplierConfiguration.supplierProductNumber';
    public const COLUMN_MINIMUM_PURCHASE = 'productSupplierConfiguration.minPurchase';
    public const COLUMN_PURCHASE_STEPS = 'productSupplierConfiguration.purchaseSteps';
    public const COLUMN_DEFAULT_SUPPLIER = 'productSupplierConfiguration.supplierIsDefault';
    public const COLUMN_DELIVERY_TIME_DAYS = 'productSupplierConfiguration.deliveryTimeDays';
    public const COLUMN_PURCHASE_PRICE_NET = 'productSupplierConfiguration.purchasePrices';
    public const COLUMN_DELETE = 'delete';
    public const COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_GTIN,
        self::COLUMN_MANUFACTURER_NAME,
        self::COLUMN_MANUFACTURER_NUMBER,
        self::COLUMN_SUPPLIER_NAME,
        self::COLUMN_SUPPLIER_NUMBER,
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_MINIMUM_PURCHASE,
        self::COLUMN_PURCHASE_STEPS,
        self::COLUMN_DELIVERY_TIME_DAYS,
        self::COLUMN_PURCHASE_PRICE_NET,
        self::COLUMN_DEFAULT_SUPPLIER,
    ];
    public const DEFAULT_COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_MANUFACTURER_NAME,
        self::COLUMN_SUPPLIER_NAME,
        self::COLUMN_SUPPLIER_NUMBER,
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_MINIMUM_PURCHASE,
        self::COLUMN_PURCHASE_STEPS,
        self::COLUMN_DELIVERY_TIME_DAYS,
        self::COLUMN_PURCHASE_PRICE_NET,
        self::COLUMN_DEFAULT_SUPPLIER,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.product-number',
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.product-name',
        self::COLUMN_GTIN => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.gtin',
        self::COLUMN_MANUFACTURER_NAME => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.manufacturer',
        self::COLUMN_MANUFACTURER_NUMBER => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.manufacturer-number',
        self::COLUMN_SUPPLIER_NAME => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.supplier',
        self::COLUMN_SUPPLIER_NUMBER => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.supplier-number',
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.supplier-product-number',
        self::COLUMN_MINIMUM_PURCHASE => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.min-purchase',
        self::COLUMN_PURCHASE_STEPS => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.purchase-steps',
        self::COLUMN_DELIVERY_TIME_DAYS => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.delivery-time-days',
        self::COLUMN_PURCHASE_PRICE_NET => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.purchase-price-net',
        self::COLUMN_DEFAULT_SUPPLIER => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.default-supplier',
        self::COLUMN_DELETE => 'pickware-erp-starter.product-supplier-configuration-list-item-export.columns.delete',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly Translator $translator,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly ProductSupplierConfigurationListItemService $productSupplierConfigurationListItemService,
        #[Autowire('%pickware_erp.import_export.profiles.product_supplier_configuration.batch_size%')]
        private readonly int $batchSize,
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

        $exportRows = $this->getProductSupplierMappingExportRows(
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
        // Product supplier configuration list items are not stored in the database but are rather constructed on-demand
        // from products. Therefore, the criteria needed to construct them with correct sorting, pagination etc. are
        // product criteria. Because we need product criteria, we also have to provide the product definition as the
        // entity definition for this export profile.
        return ProductDefinition::class;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.product-supplier-configuration-list-item-export.file-name'),
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

        $headerTranslations = $this->getCsvHeaderTranslations($export->getConfig()['locale'], $context);
        $columns = $this->getExportColumns($export);

        $translatedColumns = array_map(
            fn(string $column) => $headerTranslations[$column],
            $columns,
        );

        return [$translatedColumns];
    }

    private function getExportColumns(ImportExportEntity $export): array
    {
        $columns = $export->getConfig()['columns'] ?? self::DEFAULT_COLUMNS;
        // The administration provides the exports columns based on the user's settings. If the multiple suppliers per
        // product feature flag used to be activated at some point and the default supplier column was selected, this
        // user setting persists after deactivating the feature flag. To make sure that the default supplier column is
        // never exported when the feature flag is disabled, we explicitly remove it here.
        if (!$this->featureFlagService->isActive(MultipleSuppliersPerProductProductionFeatureFlag::NAME)) {
            $columns = array_values(array_diff($columns, [
                self::COLUMN_DEFAULT_SUPPLIER,
                self::COLUMN_DELIVERY_TIME_DAYS,
            ]));
        }

        // The "delete" column is always added to the end regardless of configuration or column settings.
        $columns[] = self::COLUMN_DELETE;

        return $columns;
    }

    /**
     * @param Criteria $criteria Only filters, sorting, limit and offset are respected
     */
    private function getProductSupplierMappingExportRows(Criteria $criteria, string $locale, array $columns, Context $context): array
    {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);
        $criteria = EntityManager::sanitizeCriteria($criteria);

        $criteria->addAssociations([
            'pickwareErpProductSupplierConfigurations.supplier',
            'pickwareErpPickwareProduct',
            'manufacturer',
            'options',
        ]);

        /** @var ProductSupplierConfigurationListItemCollection $productSupplierConfigurationListItems */
        $productSupplierConfigurationListItems = $context->enableInheritance(fn(Context $inheritanceContext) => $this->productSupplierConfigurationListItemService->getProductSupplierConfigurationListItemCollection(
            $criteria,
            $inheritanceContext,
        ));

        $productIds = array_unique($productSupplierConfigurationListItems->map(
            fn($productSupplierConfigurationListItem) => $productSupplierConfigurationListItem->getProductId(),
        ));
        $productNames = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $context);

        $trueTranslation = $this->translator->translate('pickware-erp-starter.product-supplier-configuration-list-item-export.boolean-column.true');
        $falseTranslation = $this->translator->translate('pickware-erp-starter.product-supplier-configuration-list-item-export.boolean-column.false');

        $rows = [];
        foreach ($productSupplierConfigurationListItems as $productSupplierConfigurationListItem) {
            $product = $productSupplierConfigurationListItem->getProduct();
            $productSupplierConfiguration = $productSupplierConfigurationListItem->getProductSupplierConfiguration();
            $supplier = $productSupplierConfiguration?->getSupplier();

            // Takes first purchasePrice if existent, see pw-erp-purchase-price-cell.vue value for reference
            $purchasePrices = $productSupplierConfiguration?->getPurchasePrices();
            $purchasePrice = $purchasePrices?->first();

            /** @var PickwareProductEntity $pickwareProduct */
            $pickwareProduct = $product->getExtension('pickwareErpPickwareProduct');
            $supplierIsDefault = (
                $productSupplierConfiguration
                && $productSupplierConfiguration->getSupplierId() === $pickwareProduct->getDefaultSupplierId()
            );

            $columnValues = [
                self::COLUMN_PRODUCT_NUMBER => $product->getProductNumber(),
                self::COLUMN_PRODUCT_NAME => $productNames[$product->getId()],
                self::COLUMN_GTIN => $product->getEan() ?? '',
                self::COLUMN_MANUFACTURER_NAME => $product->getManufacturer()?->getName() ?? '',
                self::COLUMN_MANUFACTURER_NUMBER => $product->getManufacturerNumber() ?? '',
                self::COLUMN_SUPPLIER_NAME => $supplier?->getName() ?? '',
                self::COLUMN_SUPPLIER_NUMBER => $supplier?->getNumber() ?? '',
                self::COLUMN_SUPPLIER_PRODUCT_NUMBER => $productSupplierConfiguration?->getSupplierProductNumber() ?? '',
                self::COLUMN_MINIMUM_PURCHASE => $productSupplierConfiguration?->getMinPurchase() ?? '',
                self::COLUMN_PURCHASE_STEPS => $productSupplierConfiguration?->getPurchaseSteps() ?? '',
                self::COLUMN_DELIVERY_TIME_DAYS => $productSupplierConfiguration?->getDeliveryTimeDays() ?? '',
                self::COLUMN_PURCHASE_PRICE_NET => $purchasePrice?->getNet() ?? '',
                self::COLUMN_DEFAULT_SUPPLIER => $supplierIsDefault ? $trueTranslation : $falseTranslation,
                self::COLUMN_DELETE => '', // Instead of a "no" translation we export an empty colum, which is interpreted as no/false when imported.
            ];

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
}
