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

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogEntryMessage;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogLevel;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierCollection;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierEntity;
use Pickware\PickwareErpStarter\Supplier\MultipleSuppliersPerProductProductionFeatureFlag;
use Pickware\ValidationBundle\JsonValidator;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

#[AutoconfigureTag('pickware_erp.import_export.importer', attributes: ['profileTechnicalName' => 'product-supplier-configuration-list-item'])]
class ProductSupplierConfigurationListItemImporter implements Importer
{
    public const TECHNICAL_NAME = 'product-supplier-configuration-list-item';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--product-supplier-configuration-list-item-import',
        'type' => 'object',
        'properties' => [
            'productNumber' => [
                'type' => 'string',
            ],
            'productName' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'ean' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'manufacturer' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'manufacturerProductNumber' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'supplier' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'supplierNumber' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'supplierProductNumber' => [
                'type' => 'string',
                'maxLength' => 64, // This limit is set by the database field
            ],
            'defaultSupplier' => [
                'type' => 'boolean',
            ],
            'minPurchase' => [
                '$ref' => '#/definitions/integerMinOneOrEmpty',
            ],
            'purchaseSteps' => [
                '$ref' => '#/definitions/integerMinOneOrEmpty',
            ],
            'deliveryTimeDays' => [
                '$ref' => '#/definitions/integerMinZeroOrEmpty',
            ],
            'purchasePriceNet' => [
                '$ref' => '#/definitions/numberMinZeroOrEmpty',
            ],
            'delete' => [
                'type' => 'boolean',
            ],
        ],
        'required' => ['productNumber'],
        'definitions' => [
            'empty' => [
                'type' => 'string',
                'maxLength' => 0,
            ],
            'integerMinZeroOrEmpty' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                ],
            ],
            'integerMinOneOrEmpty' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    [
                        'type' => 'integer',
                        'minimum' => 1,
                    ],
                ],
            ],
            'numberMinZeroOrEmpty' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    [
                        'type' => 'number',
                        'minimum' => 0,
                    ],
                ],
            ],
        ],
    ];

    private Validator $validator;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductSupplierConfigurationListItemImportCsvRowNormalizer $normalizer,
        private readonly ImportExportStateService $importExportStateService,
        private readonly FeatureFlagService $featureFlagService,
        #[Autowire('%pickware_erp.import_export.profiles.product_supplier_configuration.batch_size%')]
        private readonly int $batchSize,
        JsonValidator $jsonValidator,
    ) {
        $this->validator = new Validator($normalizer, self::VALIDATION_SCHEMA, $jsonValidator);
    }

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

        $originalColumnNamesByNormalizedColumnNames = $this->normalizer->mapNormalizedToOriginalColumnNames($headerRow);
        if (
            !$this->featureFlagService->isActive(MultipleSuppliersPerProductProductionFeatureFlag::NAME)
            && array_key_exists('defaultSupplier', $originalColumnNamesByNormalizedColumnNames)
        ) {
            $errors->addError(ProductSupplierConfigurationException::createProductDefaultSupplierColumnPresentError());
        }

        return $errors;
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
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
        $productsByProductNumber = $this->getProductsByProductNumber($normalizedRows, $context);
        $suppliers = $this->getSuppliers($normalizedRows, $context);
        $manufacturersByName = $this->getManufacturersByName($normalizedRows, $context);
        $productDefaultSupplierConfigurationsByProductId = $this->getProductDefaultSupplierConfigurationsByProductIds(
            $productsByProductNumber,
            $context,
        );
        $newProductDefaultSupplierConfigurationPayloadsByProductId = [];

        $originalColumnNamesByNormalizedColumnNames = $this->normalizer->mapNormalizedToOriginalColumnNames(array_keys(
            $importElements->first()->getRowData(),
        ));

        $importLogEntries = [];

        foreach ($importElements->getElements() as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];

            $errors = $this->validator->validateRow($normalizedRow, $originalColumnNamesByNormalizedColumnNames);
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            /** @var ?ProductEntity $product */
            $product = null;
            if (isset($normalizedRow['productNumber'])) {
                $product = $productsByProductNumber[$normalizedRow['productNumber']] ?? null;
            }
            if (!$product) {
                $errors->addError(ProductSupplierConfigurationException::createProductNotFoundError(
                    $normalizedRow['productNumber'],
                ));
            }
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            try {
                $rowImportResult = null;
                if ($this->featureFlagService->isActive(MultipleSuppliersPerProductProductionFeatureFlag::NAME)) {
                    $rowImportResult = $this->getProductSupplierConfigurationUpdatePayloadForMultipleSuppliersPerProduct(
                        $importElement,
                        $product,
                        $normalizedRow,
                        $suppliers,
                        $errors,
                        $importLogEntries,
                        $importId,
                        $context,
                    );
                } else {
                    $rowImportResult = $this->getProductSupplierConfigurationUpdatePayloadForSingleSupplierPerProduct(
                        $importElement,
                        $product,
                        $normalizedRow,
                        $suppliers,
                        $productDefaultSupplierConfigurationsByProductId,
                        $newProductDefaultSupplierConfigurationPayloadsByProductId,
                        $errors,
                        $importLogEntries,
                        $importId,
                        $context,
                    );
                }

                $updatedProduct = $this->getProductUpdatePayload(
                    $product,
                    $importElement->getId(),
                    $normalizedRow,
                    $manufacturersByName,
                    $errors,
                    $context,
                );

                $this->entityManager->runInTransactionWithRetry(
                    function() use (
                        $rowImportResult,
                        $updatedProduct,
                        $context,
                    ): void {
                        match ($rowImportResult->action) {
                            RowImportAction::Create => $this->entityManager->create(
                                ProductSupplierConfigurationDefinition::class,
                                [$rowImportResult->payload],
                                $context,
                            ),
                            RowImportAction::Update => $this->entityManager->update(
                                ProductSupplierConfigurationDefinition::class,
                                [$rowImportResult->payload],
                                $context,
                            ),
                            RowImportAction::Delete => $this->entityManager->delete(
                                ProductSupplierConfigurationDefinition::class,
                                [$rowImportResult->payload],
                                $context,
                            ),
                            RowImportAction::Skip => null,
                        };

                        if ($updatedProduct !== null) {
                            $this->entityManager->update(
                                ProductDefinition::class,
                                [$updatedProduct],
                                $context,
                            );
                        }
                    },
                );
            } catch (Throwable $exception) {
                throw ImportException::rowImportError($exception, $importElement->getRowNumber());
            }
        }

        $this->entityManager->create(
            ImportExportLogEntryDefinition::class,
            $importLogEntries,
            $context,
        );

        if ($importElements->count() < $this->batchSize) {
            return null;
        }

        $nextRowNumberToRead += $this->batchSize;

        return $nextRowNumberToRead;
    }

    private function getProductsByProductNumber(array $normalizedRows, Context $context): array
    {
        $productNumbersFromNormalizedRows = array_column($normalizedRows, 'productNumber');
        /** @var ProductCollection $products */
        $products = $context->enableInheritance(fn(Context $inheritedContext) => $this->entityManager->findBy(
            ProductDefinition::class,
            ['productNumber' => $productNumbersFromNormalizedRows],
            $inheritedContext,
            [
                'tax',
                'extensions.pickwareErpPickwareProduct',
            ],
        ));

        $productNumbers = $products->map(fn(ProductEntity $product) => $product->getProductNumber());

        return array_combine($productNumbers, $products->getElements());
    }

    private function getSuppliers(array $normalizedRows, Context $context): SupplierCollection
    {
        $supplierNumbersFromNormalizedRows = array_column($normalizedRows, 'supplierNumber');
        $supplierNamesFromNormalizedRows = array_column($normalizedRows, 'supplier');

        return new SupplierCollection($this->entityManager->findBy(
            SupplierDefinition::class,
            (new Criteria())->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsAnyFilter('number', $supplierNumbersFromNormalizedRows),
                new EqualsAnyFilter('name', $supplierNamesFromNormalizedRows),
            ])),
            $context,
        ));
    }

    private function getManufacturersByName(array $normalizedRows, Context $context): array
    {
        $manufacturerNamesFromNormalizedRows = array_column($normalizedRows, 'manufacturer');
        /** @var ProductManufacturerCollection $manufacturers */
        $manufacturers = $this->entityManager->findBy(
            ProductManufacturerDefinition::class,
            ['name' => $manufacturerNamesFromNormalizedRows],
            $context,
        );

        $manufacturerNames = $manufacturers->map(fn(ProductManufacturerEntity $manufacturerEntity) => $manufacturerEntity->getName());

        return array_combine($manufacturerNames, $manufacturers->getElements());
    }

    private function getProductDefaultSupplierConfigurationsByProductIds(array $productsByProductNumber, Context $context): array
    {
        $productIdsFromNormalizedRows = array_map(fn(ProductEntity $product) => $product->getId(), $productsByProductNumber);
        /** @var ProductSupplierConfigurationCollection $productDefaultSupplierConfigurations */
        $productDefaultSupplierConfigurations = $this->entityManager->findBy(
            ProductSupplierConfigurationDefinition::class,
            [
                'productId' => $productIdsFromNormalizedRows,
                'supplierIsDefault' => true,
            ],
            $context,
        );

        $productIds = $productDefaultSupplierConfigurations->map(
            fn(ProductSupplierConfigurationEntity $productSupplierConfiguration) => $productSupplierConfiguration->getProductId(),
        );

        return array_combine($productIds, $productDefaultSupplierConfigurations->getElements());
    }

    private function getProductSupplierConfigurationUpdatePayloadForSingleSupplierPerProduct(
        ImportExportElementEntity $importElement,
        ProductEntity $product,
        array $normalizedRow,
        SupplierCollection $suppliers,
        array $productDefaultSupplierConfigurationsByProductId,
        array &$newProductDefaultSupplierConfigurationPayloadsByProductId,
        JsonApiErrors $errors,
        array &$importLogEntries,
        string $importId,
        Context $context,
    ): ProductSupplierConfigurationListItemRowImportResult {
        $productSupplierConfigurationPayload = [];

        $productDefaultSupplierConfigurationId = null;

        $configurationAlreadyExists = isset($productDefaultSupplierConfigurationsByProductId[$product->getId()]);
        if ($configurationAlreadyExists) {
            /** @var ProductSupplierConfigurationEntity $productDefaultSupplierConfiguration */
            $productDefaultSupplierConfiguration = $productDefaultSupplierConfigurationsByProductId[$product->getId()];
            $productDefaultSupplierConfigurationId = $productDefaultSupplierConfiguration->getId();
        } elseif (isset($newProductDefaultSupplierConfigurationPayloadsByProductId[$product->getId()])) {
            $newProductDefaultSupplierConfigurationPayload = $newProductDefaultSupplierConfigurationPayloadsByProductId[$product->getId()];
            $productDefaultSupplierConfigurationId = $newProductDefaultSupplierConfigurationPayload['id'];
        }

        $supplier = $this->getSupplierFromRow($normalizedRow, $suppliers, $errors);
        if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
            return new ProductSupplierConfigurationListItemRowImportResult(
                action: RowImportAction::Skip,
            );
        }

        if ($productDefaultSupplierConfigurationId === null && $supplier === null) {
            $productSupplierConfigurationRelevantValuesWereSet = (
                isset($normalizedRow['supplierProductNumber'])
                || $this->columnHasNonEmptyValue($normalizedRow, 'minPurchase')
                || $this->columnHasNonEmptyValue($normalizedRow, 'purchaseSteps')
                || $this->columnHasNonEmptyValue($normalizedRow, 'purchasePrices')
            );
            // If the user provided any product supplier configuration relevant value without a supplier, we log a
            // warning about the inability to update the product supplier configuration.
            if ($productSupplierConfigurationRelevantValuesWereSet) {
                $importLogEntries[] = $this->createNoSupplierProvidedLogEntryPayload($importId, $importElement->getRowNumber());
            }

            // If there is no product default supplier we need to create a new one which is not possible without a
            // supplier.
            return new ProductSupplierConfigurationListItemRowImportResult(
                action: RowImportAction::Skip,
            );
        }
        if ($supplier) {
            $productSupplierConfigurationPayload['supplierId'] = $supplier->getId();
        }

        // At this point we know that there is either a product default supplier configuration or a supplier provided in
        // the row. If no supplier was provided, we can still update the product default supplier configuration.

        if (isset($normalizedRow['minPurchase'])) {
            $productSupplierConfigurationPayload['minPurchase'] = $this->getValueWithDefault($normalizedRow, 'minPurchase', ProductSupplierConfigurationDefinition::DEFAULT_MIN_PURCHASE);
        }
        if (isset($normalizedRow['purchaseSteps'])) {
            $productSupplierConfigurationPayload['purchaseSteps'] = $this->getValueWithDefault($normalizedRow, 'purchaseSteps', ProductSupplierConfigurationDefinition::DEFAULT_PURCHASE_STEPS);
        }
        if (isset($normalizedRow['purchasePriceNet'])) {
            $productSupplierConfigurationPayload['purchasePrices'] = $this->getPurchasePricesPayloadFromRow($normalizedRow, $product);
        }
        if (isset($normalizedRow['supplierProductNumber'])) {
            $productSupplierConfigurationPayload['supplierProductNumber'] = $this->getValueWithDefault($normalizedRow, 'supplierProductNumber', defaultValue: null);
        }

        $deleteConfiguration = isset($normalizedRow['delete']) && $normalizedRow['delete'];
        if ($deleteConfiguration) {
            if (!$configurationAlreadyExists) {
                // If the configuration should be deleted, but the row could not be matched to any existing
                // configuration we cannot delete anything and skip this row.
                return new ProductSupplierConfigurationListItemRowImportResult(
                    action: RowImportAction::Skip,
                );
            }

            return new ProductSupplierConfigurationListItemRowImportResult(
                action: RowImportAction::Delete,
                payload: ['id' => $productDefaultSupplierConfigurationId],
            );
        }

        if (count($productSupplierConfigurationPayload) === 0) {
            return new ProductSupplierConfigurationListItemRowImportResult(
                action: RowImportAction::Skip,
            );
        }

        $productSupplierConfigurationPayload['id'] = $configurationAlreadyExists ? $productDefaultSupplierConfigurationId : Uuid::randomHex();
        $productSupplierConfigurationPayload['productId'] = $product->getId();
        $productSupplierConfigurationPayload['productVersionId'] = $product->getVersionId();
        if (!$configurationAlreadyExists) {
            $newProductDefaultSupplierConfigurationPayloadsByProductId[$product->getId()] = $productSupplierConfigurationPayload;
        }

        return new ProductSupplierConfigurationListItemRowImportResult(
            action: $configurationAlreadyExists ? RowImportAction::Update : RowImportAction::Create,
            payload: $productSupplierConfigurationPayload,
        );
    }

    private function getProductSupplierConfigurationUpdatePayloadForMultipleSuppliersPerProduct(
        ImportExportElementEntity $importElement,
        ProductEntity $product,
        array $normalizedRow,
        SupplierCollection $suppliers,
        JsonApiErrors $errors,
        array &$importLogEntries,
        string $importId,
        Context $context,
    ): ProductSupplierConfigurationListItemRowImportResult {
        $productSupplierConfigurationPayload = [];

        $supplier = $this->getSupplierFromRow($normalizedRow, $suppliers, $errors);
        if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
            return new ProductSupplierConfigurationListItemRowImportResult(
                action: RowImportAction::Skip,
            );
        }
        if ($supplier === null) {
            $productSupplierConfigurationRelevantValuesWereSet = (
                isset($normalizedRow['supplierProductNumber'])
                || isset($normalizedRow['defaultSupplier'])
                || $this->columnHasNonEmptyValue($normalizedRow, 'minPurchase')
                || $this->columnHasNonEmptyValue($normalizedRow, 'purchaseSteps')
                || $this->columnHasNonEmptyValue($normalizedRow, 'purchasePrices')
            );
            // If the user provided any product supplier configuration relevant value without a supplier, we log a
            // warning about the inability to update the product supplier configuration.
            if ($productSupplierConfigurationRelevantValuesWereSet) {
                $importLogEntries[] = $this->createNoSupplierProvidedLogEntryPayload($importId, $importElement->getRowNumber());
            }

            // In case no supplier was provided, we don't know which product supplier configuration to update, so we
            // skip the update.
            return new ProductSupplierConfigurationListItemRowImportResult(
                action: RowImportAction::Skip,
            );
        }
        $productSupplierConfigurationPayload['supplierId'] = $supplier->getId();

        if (isset($normalizedRow['minPurchase'])) {
            $productSupplierConfigurationPayload['minPurchase'] = $this->getValueWithDefault($normalizedRow, 'minPurchase', ProductSupplierConfigurationDefinition::DEFAULT_MIN_PURCHASE);
        }
        if (isset($normalizedRow['purchaseSteps'])) {
            $productSupplierConfigurationPayload['purchaseSteps'] = $this->getValueWithDefault($normalizedRow, 'purchaseSteps', ProductSupplierConfigurationDefinition::DEFAULT_PURCHASE_STEPS);
        }
        if (isset($normalizedRow['deliveryTimeDays'])) {
            $productSupplierConfigurationPayload['deliveryTimeDays'] = $this->getValueWithDefault($normalizedRow, 'deliveryTimeDays', defaultValue: null);
        }
        if (isset($normalizedRow['purchasePriceNet'])) {
            $productSupplierConfigurationPayload['purchasePrices'] = $this->getPurchasePricesPayloadFromRow($normalizedRow, $product);
        }
        if (isset($normalizedRow['supplierProductNumber'])) {
            $productSupplierConfigurationPayload['supplierProductNumber'] = $this->getValueWithDefault($normalizedRow, 'supplierProductNumber', defaultValue: null);
        }

        $isDefaultSupplier = isset($normalizedRow['defaultSupplier']) && $normalizedRow['defaultSupplier'];
        if ($isDefaultSupplier) {
            $productSupplierConfigurationPayload['product'] = [
                'id' => $product->getId(),
                'pickwareErpPickwareProduct' => [
                    'id' => $product->getExtension('pickwareErpPickwareProduct')->getId(),
                    'defaultSupplierId' => $supplier->getId(),
                ],
            ];
        }

        // The existing product supplier configurations cannot be fetched for the entire batch without potentially
        // over-fetching a large amount of data. Therefore, we fetch the existing product supplier configuration for the
        // current product and supplier here.
        /** @var ?ProductSupplierConfigurationEntity $existingProductSupplierConfiguration */
        $existingProductSupplierConfiguration = $this->entityManager->findOneBy(
            ProductSupplierConfigurationDefinition::class,
            [
                'productId' => $product->getId(),
                'supplierId' => $supplier->getId(),
            ],
            $context,
        );

        $configurationAlreadyExists = $existingProductSupplierConfiguration !== null;
        $productSupplierConfigurationPayload['id'] = $configurationAlreadyExists ? $existingProductSupplierConfiguration->getId() : Uuid::randomHex();
        $productSupplierConfigurationPayload['productId'] = $product->getId();
        $productSupplierConfigurationPayload['productVersionId'] = $product->getVersionId();

        $deleteConfiguration = isset($normalizedRow['delete']) && $normalizedRow['delete'];
        if ($deleteConfiguration) {
            if (!$configurationAlreadyExists) {
                // If the configuration should be deleted, but the row could not be matched to any existing
                // configuration we cannot delete anything and skip this row.
                return new ProductSupplierConfigurationListItemRowImportResult(
                    action: RowImportAction::Skip,
                );
            }

            return new ProductSupplierConfigurationListItemRowImportResult(
                action: RowImportAction::Delete,
                payload: ['id' => $existingProductSupplierConfiguration->getId()],
            );
        }

        return new ProductSupplierConfigurationListItemRowImportResult(
            action: $configurationAlreadyExists ? RowImportAction::Update : RowImportAction::Create,
            payload: $productSupplierConfigurationPayload,
        );
    }

    private function getPurchasePricesPayloadFromRow(
        array $normalizedRow,
        ProductEntity $product,
    ): ?array {
        $purchasePricesPayload = [ProductSupplierConfigurationDefinition::DEFAULT_PURCHASE_PRICE];
        if ($this->columnHasNonEmptyValue($normalizedRow, 'purchasePriceNet')) {
            $net = $normalizedRow['purchasePriceNet'];
            $gross = $net * (1 + $product->getTax()->getTaxRate() / 100);

            $purchasePrices = new PriceCollection();
            $purchasePrices->add(new Price(Defaults::CURRENCY, $net, $gross, linked: true));

            $purchasePricesPayload = $purchasePrices->map(fn($price) => $price->jsonSerialize());
        }

        return $purchasePricesPayload;
    }

    private function getSupplierFromRow(
        array $normalizedRow,
        SupplierCollection $suppliers,
        JsonApiErrors $errors,
    ): ?SupplierEntity {
        $supplier = null;
        $hasSupplierNumberValue = $this->columnHasNonEmptyValue($normalizedRow, 'supplierNumber');
        $hasSupplierNameValue = $this->columnHasNonEmptyValue($normalizedRow, 'supplier');
        if ($hasSupplierNumberValue) {
            $supplierNumber = $normalizedRow['supplierNumber'];
            $supplier = $suppliers->filter(fn(SupplierEntity $supplier) => $supplier->getNumber() === $supplierNumber)->first();
            if ($supplier === null) {
                $errors->addError(
                    ProductSupplierConfigurationException::createSupplierNotFoundByNumberError($supplierNumber),
                );
            }
        } elseif ($hasSupplierNameValue) {
            $supplierName = $normalizedRow['supplier'];
            $supplier = $suppliers->filter(fn(SupplierEntity $supplier) => $supplier->getName() === $supplierName)->first();
            if ($supplier === null) {
                $errors->addError(
                    ProductSupplierConfigurationException::createSupplierNotFoundByNameError($supplierName),
                );
            }
        }

        return $supplier;
    }

    private function getValueWithDefault(array $normalizedRow, string $normalizedColumnName, mixed $defaultValue): mixed
    {
        $value = $defaultValue;
        if ($this->columnHasNonEmptyValue($normalizedRow, $normalizedColumnName)) {
            $value = $normalizedRow[$normalizedColumnName];
        }

        return $value;
    }

    private function columnHasNonEmptyValue(array $normalizedRow, string $normalizedColumnName): bool
    {
        return isset($normalizedRow[$normalizedColumnName]) && $normalizedRow[$normalizedColumnName] !== '';
    }

    private function getProductUpdatePayload(
        ProductEntity $product,
        string $importElementId,
        array $normalizedRow,
        array $manufacturersByName,
        JsonApiErrors $errors,
        Context $context,
    ): ?array {
        if (count($errors) > 0) {
            return null;
        }

        $payload = ['id' => $product->getId()];

        if (isset($normalizedRow['manufacturer'])) {
            $payload['manufacturerId'] = $this->getManufacturerFromRow($normalizedRow, $manufacturersByName, $errors)?->getId();
        }
        if ($this->failOnErrors($importElementId, $errors, $context)) {
            return null;
        }

        if (isset($normalizedRow['gtin'])) {
            $payload['ean'] = $this->getValueWithDefault($normalizedRow, 'gtin', defaultValue: null);
        }

        if (isset($normalizedRow['manufacturerProductNumber'])) {
            $payload['manufacturerNumber'] = $this->getValueWithDefault($normalizedRow, 'manufacturerProductNumber', defaultValue: null);
        }

        return $payload;
    }

    private function getManufacturerFromRow(
        array $normalizedRow,
        array $manufacturersByName,
        JsonApiErrors $errors,
    ): ?ProductManufacturerEntity {
        $manufacturer = null;
        $hasManufacturerValue = $this->columnHasNonEmptyValue($normalizedRow, 'manufacturer');
        if ($hasManufacturerValue) {
            /** @var string $manufacturerName */
            $manufacturerName = $normalizedRow['manufacturer'];
            if (array_key_exists($manufacturerName, $manufacturersByName)) {
                $manufacturer = $manufacturersByName[$manufacturerName];
            } else {
                $errors->addError(
                    ProductSupplierConfigurationException::createManufacturerNotFoundError(
                        $normalizedRow['manufacturer'],
                    ),
                );
            }
        }

        return $manufacturer;
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

    private function createNoSupplierProvidedLogEntryPayload(string $importId, int $rowNumber): array
    {
        return [
            'id' => Uuid::randomHex(),
            'importExportId' => $importId,
            'logLevel' => ImportExportLogLevel::Warning,
            'rowNumber' => $rowNumber,
            'message' => ImportExportLogEntryMessage::fromJsonApiError(
                ProductSupplierConfigurationException::createNoSupplierProvidedColumnError(),
            ),
        ];
    }
}
