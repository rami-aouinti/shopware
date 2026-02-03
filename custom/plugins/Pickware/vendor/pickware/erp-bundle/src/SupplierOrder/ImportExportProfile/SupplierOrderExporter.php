<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\ImportExportProfile;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemCollection;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use RuntimeException;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'supplier-order'])]
class SupplierOrderExporter implements Exporter, FileExporter, HeaderExporter
{
    public const SYSTEM_CONFIG_CSV_EXPORT_COLUMNS_KEY = 'PickwareErpBundle.global-plugin-config.supplierOrderCsvExportColumns';
    public const TECHNICAL_NAME = 'supplier-order';
    public const COLUMN_SUPPLIER_PRODUCT_NUMBER = 'product.extensions.pickwareErpProductSupplierConfigurations.supplierProductNumber';
    public const COLUMN_DELIVERED_QUANTITY = 'deliveredQuantity';
    public const COLUMN_GTIN = 'product.ean';
    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_MANUFACTURER_NAME = 'product.manufacturer.name';
    public const COLUMN_MANUFACTURER_NUMBER = 'product.manufacturerNumber';
    public const COLUMN_AVAILABLE_STOCK = 'product.availableStock';
    public const COLUMN_MINIMUM_PURCHASE = 'product.extensions.pickwareErpProductSupplierConfigurations.minPurchase';
    public const COLUMN_PURCHASE_STEPS = 'product.extensions.pickwareErpProductSupplierConfigurations.purchaseSteps';
    public const COLUMN_QUANTITY = 'quantity';
    public const COLUMN_EXPECTED_DELIVERY_DATE = 'expectedDeliveryDate';
    public const COLUMN_ACTUAL_DELIVERY_DATE = 'actualDeliveryDate';
    public const COLUMN_UNIT_PRICE = 'unitPrice';
    public const COLUMN_UNIT_PRICE_CALCULATED_BY_TAX_STATUS = 'unitPriceCalculatedByTaxStatus';
    public const COLUMN_TOTAL_PRICE = 'totalPrice';
    public const COLUMN_TOTAL_PRICE_CALCULATED_BY_TAX_STATUS = 'totalPriceCalculatedByTaxStatus';
    public const COLUMNS = [
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_DELIVERED_QUANTITY,
        self::COLUMN_GTIN,
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_MANUFACTURER_NAME,
        self::COLUMN_MANUFACTURER_NUMBER,
        self::COLUMN_AVAILABLE_STOCK,
        self::COLUMN_MINIMUM_PURCHASE,
        self::COLUMN_PURCHASE_STEPS,
        self::COLUMN_QUANTITY,
        self::COLUMN_EXPECTED_DELIVERY_DATE,
        self::COLUMN_ACTUAL_DELIVERY_DATE,
        self::COLUMN_UNIT_PRICE,
        self::COLUMN_UNIT_PRICE_CALCULATED_BY_TAX_STATUS,
        self::COLUMN_TOTAL_PRICE,
        self::COLUMN_TOTAL_PRICE_CALCULATED_BY_TAX_STATUS,
    ];
    public const GRID_EXPORT_DEFAULT_COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_QUANTITY,
        self::COLUMN_DELIVERED_QUANTITY,
        self::COLUMN_EXPECTED_DELIVERY_DATE,
        self::COLUMN_ACTUAL_DELIVERY_DATE,
        self::COLUMN_UNIT_PRICE,
        self::COLUMN_TOTAL_PRICE,
    ];
    public const EMAIL_CSV_EXPORT_DEFAULT_COLUMNS = [
        'supplier-product-number',
        'ean',
        'product-name',
        'quantity',
    ];

    // Virtual columns
    public const VIRTUAL_COLUMN_UNIT_PRICE_NET = 'unitPriceNet';
    public const VIRTUAL_COLUMN_UNIT_PRICE_GROSS = 'unitPriceGross';
    public const VIRTUAL_COLUMN_TOTAL_PRICE_NET = 'totalPriceNet';
    public const VIRTUAL_COLUMN_TOTAL_PRICE_GROSS = 'totalPriceGross';
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER => 'pickware-erp-starter.supplier-order-export.columns.supplier-product-number',
        self::COLUMN_GTIN => 'pickware-erp-starter.supplier-order-export.columns.gtin',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.supplier-order-export.columns.product-number',
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.supplier-order-export.columns.product-name',
        self::COLUMN_MANUFACTURER_NAME => 'pickware-erp-starter.supplier-order-export.columns.manufacturer-name',
        self::COLUMN_MANUFACTURER_NUMBER => 'pickware-erp-starter.supplier-order-export.columns.manufacturer-number',
        self::COLUMN_AVAILABLE_STOCK => 'pickware-erp-starter.supplier-order-export.columns.available-stock',
        self::COLUMN_MINIMUM_PURCHASE => 'pickware-erp-starter.supplier-order-export.columns.min-purchase',
        self::COLUMN_PURCHASE_STEPS => 'pickware-erp-starter.supplier-order-export.columns.purchase-steps',
        self::COLUMN_QUANTITY => 'pickware-erp-starter.supplier-order-export.columns.quantity',
        self::COLUMN_EXPECTED_DELIVERY_DATE => 'pickware-erp-starter.supplier-order-export.columns.expected-delivery-date',
        self::COLUMN_ACTUAL_DELIVERY_DATE => 'pickware-erp-starter.supplier-order-export.columns.actual-delivery-date',
        self::COLUMN_DELIVERED_QUANTITY => 'pickware-erp-starter.supplier-order-export.columns.delivered-quantity',
        self::VIRTUAL_COLUMN_UNIT_PRICE_NET => 'pickware-erp-starter.supplier-order-export.columns.unit-price-net',
        self::VIRTUAL_COLUMN_UNIT_PRICE_GROSS => 'pickware-erp-starter.supplier-order-export.columns.unit-price-gross',
        self::VIRTUAL_COLUMN_TOTAL_PRICE_NET => 'pickware-erp-starter.supplier-order-export.columns.total-price-net',
        self::VIRTUAL_COLUMN_TOTAL_PRICE_GROSS => 'pickware-erp-starter.supplier-order-export.columns.total-price-gross',
    ];
    public const DELETED_TRANSLATION = 'pickware-erp-starter.supplier-order-export.deleted';
    public const COLUMN_IDENTIFIER_MAPPING = [
        'supplier-product-number' => self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        'ean' => self::COLUMN_GTIN,
        'product-number' => self::COLUMN_PRODUCT_NUMBER,
        'product-name' => self::COLUMN_PRODUCT_NAME,
        'manufacturer' => self::COLUMN_MANUFACTURER_NAME,
        'manufacturer-number' => self::COLUMN_MANUFACTURER_NUMBER,
        'available-stock' => self::COLUMN_AVAILABLE_STOCK,
        'min-purchase' => self::COLUMN_MINIMUM_PURCHASE,
        'purchase-steps' => self::COLUMN_PURCHASE_STEPS,
        'quantity' => self::COLUMN_QUANTITY,
        'expected-delivery-date' => self::COLUMN_EXPECTED_DELIVERY_DATE,
        'actual-delivery-date' => self::COLUMN_ACTUAL_DELIVERY_DATE,
        'delivered-quantity' => self::COLUMN_DELIVERED_QUANTITY,
        'unit-price' => self::COLUMN_UNIT_PRICE,
        'unit-price-calculated-by-tax-status' => self::COLUMN_UNIT_PRICE_CALCULATED_BY_TAX_STATUS,
        'total-price' => self::COLUMN_TOTAL_PRICE,
        'total-price-calculated-by-tax-status' => self::COLUMN_TOTAL_PRICE_CALCULATED_BY_TAX_STATUS,
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly Translator $translator,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly SupplierOrderExportColumnService $supplierOrderExportColumnService,
        #[Autowire('%pickware_erp.import_export.profiles.supplier_order.batch_size%')]
        private readonly int $batchSize,
    ) {}

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);

        $criteria = $this->getCriteria($export, 0);
        $supplierOrderLineItems = $this->getSupplierOrderLineItems($criteria, $context);
        $supplierOrder = $supplierOrderLineItems->first() ? $supplierOrderLineItems->first()->getSupplierOrder() : null;

        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return vsprintf(
            $this->translator->translate('pickware-erp-starter.supplier-order-export.file-name'),
            [
                $supplierOrder ? $supplierOrder->getNumber() : '',
                $export->getCreatedAt()->format('Y-m-d H_i_s'),
            ],
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

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria = $this->getCriteria($export, $nextRowNumberToWrite - 1);
        $exportRows = $this->getSupplierOrderExportRows(
            $criteria,
            $exportConfig['locale'],
            $this->supplierOrderExportColumnService->getColumns($exportConfig),
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

        $this->entityManager->create(ImportExportElementDefinition::class, $exportElementPayloads, $context);

        $nextRowNumberToWrite += $this->batchSize;

        if (count($exportRows) < $this->batchSize) {
            return null;
        }

        return $nextRowNumberToWrite;
    }

    public function getEntityDefinitionClassName(): string
    {
        return SupplierOrderLineItemDefinition::class;
    }

    public function getHeader(string $exportId, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $criteria = $this->getCriteria($export, 0);
        $criteria->setLimit(1);
        $supplierOrderLineItems = $this->getSupplierOrderLineItems($criteria, $context);
        $isNetOrder = true;
        if ($supplierOrderLineItems->count() > 0) {
            $isNetOrder = $this->isNetSupplierOrder($supplierOrderLineItems->first()->getSupplierOrder());
        }

        $headerTranslations = $this->getCsvHeaderTranslations($isNetOrder, $export->getConfig()['locale'], $context);
        $translatedColumns = array_map(
            fn(string $column) => $headerTranslations[$column],
            $this->supplierOrderExportColumnService->getColumns($export->getConfig()),
        );

        return [$translatedColumns];
    }

    private function getSupplierOrderExportRows(Criteria $criteria, string $locale, array $columns, Context $context): array
    {
        $supplierOrderLineItems = $this->getSupplierOrderLineItems($criteria, $context);
        $supplierOrderId = $supplierOrderLineItems->first()?->getSupplierOrderId();
        $goodsReceipts = doIf($supplierOrderId, fn($supplierOrderId) => $this->getGoodsReceiptsForSupplierOrder($supplierOrderId, $context));
        $deletedTranslation = $this->getDeletedTranslations($locale, $context);
        $isNetSupplierOrder = true;
        if ($supplierOrderLineItems->count() > 0) {
            $isNetSupplierOrder = $this->isNetSupplierOrder($supplierOrderLineItems->first()->getSupplierOrder());
        }
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($isNetSupplierOrder, $locale, $context);

        // We need to filter the supplier order line items to only get product ids if they are set
        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            array_map(
                fn(SupplierOrderLineItemEntity $supplierOrderLineItemEntity) => $supplierOrderLineItemEntity->getProductId(),
                array_filter(
                    $supplierOrderLineItems->getElements(),
                    fn(SupplierOrderLineItemEntity $supplierOrderLineItemEntity) => $supplierOrderLineItemEntity->getProductId() != null,
                ),
            ),
            [],
            $context,
        );

        $rows = [];
        foreach ($supplierOrderLineItems as $supplierOrderLineItem) {
            $product = $supplierOrderLineItem->getProduct();
            $productSnapshot = $supplierOrderLineItem->getProductSnapshot();
            $manufacturer = $product?->getManufacturer();
            /** @var ProductSupplierConfigurationEntity $productSupplierConfiguration */
            $productSupplierConfiguration = $product
                ?->getExtension('pickwareErpProductSupplierConfigurations')
                ?->filter(
                    fn(ProductSupplierConfigurationEntity $configuration) => $configuration->getSupplierId() === $supplierOrderLineItem->getSupplierOrder()->getSupplierId(),
                )->first();
            $productSupplierConfigurationSnapshot = $supplierOrderLineItem->getProductSupplierConfigurationSnapshot();
            $deliveredQuantity = doIf($goodsReceipts, fn($goodsReceipts) => ImmutableCollection::create($goodsReceipts)
                ->flatMap(fn(GoodsReceiptEntity $goodsReceipt) => ImmutableCollection::create($goodsReceipt->getLineItems()))
                ->filter(fn(GoodsReceiptLineItemEntity $goodsReceiptLineItem) => $goodsReceiptLineItem->getProductId() === $supplierOrderLineItem->getProductId())
                ->filter(fn(GoodsReceiptLineItemEntity $goodsReceiptLineItem) => $goodsReceiptLineItem->getSupplierOrderId() === $supplierOrderId)
                ->map(fn(GoodsReceiptLineItemEntity $goodsReceiptLineItemEntity) => $goodsReceiptLineItemEntity->getQuantity())
                ->sum()) ?? 0;

            $columnValues = [
                self::COLUMN_SUPPLIER_PRODUCT_NUMBER => $productSupplierConfiguration?->getSupplierProductNumber() ?? $productSupplierConfigurationSnapshot['supplierProductNumber'] ?? null,
                self::COLUMN_GTIN => $product?->getEan() ?? '',
                self::COLUMN_PRODUCT_NUMBER => $product?->getProductNumber() ?? $productSnapshot['productNumber'],
                self::COLUMN_PRODUCT_NAME => $product ? $productNames[$product->getId()] : sprintf('%s (%s)', $productSnapshot['name'], $deletedTranslation),
                self::COLUMN_MANUFACTURER_NAME => $manufacturer?->getName() ?? '',
                self::COLUMN_MANUFACTURER_NUMBER => $product?->getManufacturerNumber() ?? '',
                self::COLUMN_AVAILABLE_STOCK => $product?->getAvailableStock() ?? null,
                self::COLUMN_MINIMUM_PURCHASE => $productSupplierConfiguration?->getMinPurchase() ?? $productSupplierConfigurationSnapshot['minPurchase'] ?? null,
                self::COLUMN_PURCHASE_STEPS => $productSupplierConfiguration?->getPurchaseSteps() ?? $productSupplierConfigurationSnapshot['purchaseSteps'] ?? null,
                self::COLUMN_QUANTITY => $supplierOrderLineItem->getQuantity(),
                self::COLUMN_EXPECTED_DELIVERY_DATE => $supplierOrderLineItem->getExpectedDeliveryDate()?->format('Y-m-d'),
                self::COLUMN_ACTUAL_DELIVERY_DATE => $supplierOrderLineItem->getActualDeliveryDate()?->format('Y-m-d'),
                self::COLUMN_DELIVERED_QUANTITY => $deliveredQuantity,
                self::COLUMN_UNIT_PRICE => $supplierOrderLineItem->getUnitPrice(),
                self::COLUMN_TOTAL_PRICE => $supplierOrderLineItem->getTotalPrice(),
                self::COLUMN_UNIT_PRICE_CALCULATED_BY_TAX_STATUS => $this->getUnitPriceCalculatedByTaxStatus($supplierOrderLineItem),
                self::COLUMN_TOTAL_PRICE_CALCULATED_BY_TAX_STATUS => $this->getTotalPriceCalculatedByTaxStatus($supplierOrderLineItem),
            ];

            $currentRow = [];
            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]]
                    = $columnValues[$column];
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }

    private function getCriteria(ImportExportEntity $export, int $offset): Criteria
    {
        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $export->getConfig()['criteria'],
            $this->getEntityDefinitionClassName(),
        );
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($offset);

        return $criteria;
    }

    private function getSupplierOrderLineItems(Criteria $criteria, Context $context): SupplierOrderLineItemCollection
    {
        /** @var SupplierOrderLineItemCollection */
        return new SupplierOrderLineItemCollection($context->enableInheritance(
            fn(Context $inheritanceContext) => $this->entityManager->findBy(
                SupplierOrderLineItemDefinition::class,
                $criteria,
                $inheritanceContext,
                [
                    'product.manufacturer',
                    'product.pickwareErpProductSupplierConfigurations',
                    'supplierOrder',
                ],
            ),
        ));
    }

    private function getGoodsReceiptsForSupplierOrder(string $supplierOrderId, Context $context): GoodsReceiptCollection
    {
        /** @var GoodsReceiptCollection */
        return $this->entityManager->findBy(
            GoodsReceiptDefinition::class,
            (new Criteria())->addFilter(new EqualsFilter('supplierOrders.id', $supplierOrderId)),
            $context,
            ['lineItems'],
        );
    }

    private function getUnitPriceCalculatedByTaxStatus(SupplierOrderLineItemEntity $supplierOrderLineItem): float
    {
        if ($supplierOrderLineItem->getQuantity() === 0) {
            throw new RuntimeException('Cannot export unit price for supplier order line item with quantity 0');
        }

        // We have no "taxes per unit", only the total tax. That is why we need to use the total and divide by quantity.
        return round($this->getTotalPriceCalculatedByTaxStatus($supplierOrderLineItem) / $supplierOrderLineItem->getQuantity(), 2);
    }

    private function getTotalPriceCalculatedByTaxStatus(SupplierOrderLineItemEntity $supplierOrderLineItem): float
    {
        $taxes = $supplierOrderLineItem->getPrice()->getCalculatedTaxes()->reduce(fn(float $taxes, CalculatedTax $calculatedTax) => $taxes + $calculatedTax->getTax(), 0);

        return $supplierOrderLineItem->getTotalPrice() + ($this->isNetSupplierOrder($supplierOrderLineItem->getSupplierOrder()) ? $taxes : -1 * $taxes);
    }

    private function getCsvHeaderTranslations(
        bool $isNetSupplierOrder,
        string $locale,
        Context $context,
    ): array {
        $this->translator->setTranslationLocale($locale, $context);

        $manuallyTranslatedSnippetKeys = [
            self::COLUMN_UNIT_PRICE,
            self::COLUMN_TOTAL_PRICE,
            self::COLUMN_UNIT_PRICE_CALCULATED_BY_TAX_STATUS,
            self::COLUMN_TOTAL_PRICE_CALCULATED_BY_TAX_STATUS,
        ];

        $translations = array_map(
            fn($snippedId) => $this->translator->translate($snippedId),
            array_filter(self::COLUMN_TRANSLATIONS, fn($key) => !in_array($key, $manuallyTranslatedSnippetKeys)),
        );

        if ($isNetSupplierOrder) {
            $unitPriceTranslationKey = self::COLUMN_TRANSLATIONS[self::VIRTUAL_COLUMN_UNIT_PRICE_NET];
            $totalPriceTranslationKey = self::COLUMN_TRANSLATIONS[self::VIRTUAL_COLUMN_TOTAL_PRICE_NET];
            $unitPriceCalculatedByTaxStatusTranslationKey = self::COLUMN_TRANSLATIONS[self::VIRTUAL_COLUMN_UNIT_PRICE_GROSS];
            $totalPriceCalculatedByTaxStatusTranslationKey = self::COLUMN_TRANSLATIONS[self::VIRTUAL_COLUMN_TOTAL_PRICE_GROSS];
        } else {
            $unitPriceTranslationKey = self::COLUMN_TRANSLATIONS[self::VIRTUAL_COLUMN_UNIT_PRICE_GROSS];
            $totalPriceTranslationKey = self::COLUMN_TRANSLATIONS[self::VIRTUAL_COLUMN_TOTAL_PRICE_GROSS];
            $unitPriceCalculatedByTaxStatusTranslationKey = self::COLUMN_TRANSLATIONS[self::VIRTUAL_COLUMN_UNIT_PRICE_NET];
            $totalPriceCalculatedByTaxStatusTranslationKey = self::COLUMN_TRANSLATIONS[self::VIRTUAL_COLUMN_TOTAL_PRICE_NET];
        }
        $translations[self::COLUMN_UNIT_PRICE] = $this->translator->translate($unitPriceTranslationKey);
        $translations[self::COLUMN_TOTAL_PRICE] = $this->translator->translate($totalPriceTranslationKey);
        $translations[self::COLUMN_UNIT_PRICE_CALCULATED_BY_TAX_STATUS] = $this->translator->translate($unitPriceCalculatedByTaxStatusTranslationKey);
        $translations[self::COLUMN_TOTAL_PRICE_CALCULATED_BY_TAX_STATUS] = $this->translator->translate($totalPriceCalculatedByTaxStatusTranslationKey);

        return $translations;
    }

    private function getDeletedTranslations(string $locale, Context $context): string
    {
        $this->translator->setTranslationLocale($locale, $context);

        return $this->translator->translate(self::DELETED_TRANSLATION);
    }

    private function isNetSupplierOrder(SupplierOrderEntity $supplierOrder): bool
    {
        return $supplierOrder->getTaxStatus() === CartPrice::TAX_STATE_NET;
    }
}
