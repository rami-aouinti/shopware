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

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogEntryMessage;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\PriceCalculation\OrderRecalculationService;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\Supplier\MultipleSuppliersPerProductProductionFeatureFlag;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemEntity;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderLineItemCreationService;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderLineItemPayloadCreationInput;
use Pickware\ValidationBundle\JsonValidator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.importer', attributes: ['profileTechnicalName' => 'supplier-order'])]
class SupplierOrderImporter implements Importer
{
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--supplier-order',
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
            'manufacturerName' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'manufacturerProductNumber' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'supplierProductNumber' => [
                'type' => 'string',
                'maxLength' => 64, // This limit is set by the database field
            ],
            'minPurchase' => [
                '$ref' => '#/definitions/integerMinOneOrEmpty',
            ],
            'purchaseSteps' => [
                '$ref' => '#/definitions/integerMinOneOrEmpty',
            ],
            'quantity' => [
                'type' => 'number',
                'minimum' => 0,
            ],
            // Note that the "unit/total price calculated by tax status" (see export profile) is not used in the import.
            'unitPrice' => [
                '$ref' => '#/definitions/numberMinZeroOrEmpty',
            ],
            'totalPrice' => [
                '$ref' => '#/definitions/numberMinZeroOrEmpty',
            ],
            'expectedDeliveryDate' => [
                '$ref' => '#/definitions/dateOrEmpty',
            ],
            'actualDeliveryDate' => [
                '$ref' => '#/definitions/dateOrEmpty',
            ],
        ],
        'required' => [
            'quantity',
        ],
        'additionalProperties' => true,
        'definitions' => [
            'empty' => [
                'type' => 'string',
                'maxLength' => 0,
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
            'dateOrEmpty' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    [
                        'type' => 'string',
                        'format' => 'date',
                    ],
                ],
            ],
        ],
    ];
    public const CONFIG_KEY_SUPPLIER_ORDER_ID = 'supplierOrderId';
    public const CONFIG_KEY_UPDATE_PRODUCT_AND_PRODUCT_SUPPLIER_CONFIGURATION = 'updateProductAndProductSupplierConfiguration';

    private Validator $validator;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly SupplierOrderImportCsvRowNormalizer $normalizer,
        private readonly ImportExportStateService $importExportStateService,
        private readonly OrderRecalculationService $orderRecalculationService,
        private readonly SupplierOrderLineItemCreationService $supplierOrderLineItemCreationService,
        private readonly CurrencyFormatter $currencyFormatter,
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
        return $this->validator->validateHeaderRow($headerRow, $context);
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
        /** @var ImportExportEntity $import */
        $import = $this->entityManager->findByPrimaryKey(
            ImportExportDefinition::class,
            $importId,
            $context,
        );
        $config = $import->getConfig();
        $supplierOrderId = $config[self::CONFIG_KEY_SUPPLIER_ORDER_ID];
        if (!$supplierOrderId) {
            throw new InvalidArgumentException('Config key "supplierOrderId" is required.');
        }

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

        /** @var SupplierOrderEntity $supplierOrder */
        $supplierOrder = $this->entityManager->getByPrimaryKey(
            SupplierOrderDefinition::class,
            $supplierOrderId,
            $context,
            [
                'lineItems',
                'supplier',
                'currency',
            ],
        );
        $allProducts = ImmutableCollection::create($this->getProducts($normalizedRows, $supplierOrder->getSupplierId(), $context));
        $allManufacturers = ImmutableCollection::create($this->getManufacturers($normalizedRows, $context));

        $originalColumnNamesByNormalizedColumnNames = $this->normalizer->mapNormalizedToOriginalColumnNames(array_keys(
            $importElements->first()->getRowData(),
        ));

        $updateProductAndSupplierConfiguration = $config[self::CONFIG_KEY_UPDATE_PRODUCT_AND_PRODUCT_SUPPLIER_CONFIGURATION] ?? false;
        $importExportLogEntryCreationPayloads = [];
        $supplierOrderLineItemUpsertPayloads = [];
        $supplierOrderLineItemCreationInputs = [];
        $deleteSupplierOrderLineItemIds = [];
        $productSupplierConfigurationUpsertPayloads = [];
        $productUpdatePayloads = [];
        /** @var ImportExportElementEntity $importElement */
        foreach ($importElements as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];
            $nonEmptyColumns = array_filter($normalizedRow, fn(mixed $data) => $data !== '');
            if (count($nonEmptyColumns) === 0) {
                // If the row is empty, we skip it.
                continue;
            }

            $errors = $this->validator->validateRow($normalizedRow, $originalColumnNamesByNormalizedColumnNames);
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            $products = $this->getProductsForImport($normalizedRow, $allProducts, $supplierOrder);
            if (count($products) > 1) {
                $importExportLogEntryCreationPayloads[] = $this->createImportLogEntryPayload(
                    $importElement,
                    SupplierOrderImportMessage::createMultipleProductsWithSameProductSupplierNumberAddedInfo(
                        $supplierOrder->getSupplier()->getName(),
                        $normalizedRow['supplierProductNumber'],
                    ),
                );
            }
            if (count($products) === 0) {
                $importExportLogEntryCreationPayloads[] = $this->createImportLogEntryPayload(
                    $importElement,
                    SupplierOrderImportMessage::createProductNotFoundError(
                        $normalizedRow['productNumber'] ?? null,
                        $normalizedRow['supplierProductNumber'] ?? null,
                    ),
                );

                continue;
            }

            /** @var ProductEntity $product */
            foreach ($products as $product) {
                $existingProductSupplierConfiguration = $this->getProductSupplierConfiguration($product, $supplierOrder->getSupplierId());
                /** @var ?SupplierOrderLineItemEntity $existingSupplierOrderLineItem */
                $existingSupplierOrderLineItem = $supplierOrder->getLineItems()->firstWhere(fn($lineItem) => $lineItem->getProductId() === $product->getId());

                if ($normalizedRow['quantity'] === 0) {
                    if ($existingSupplierOrderLineItem) {
                        // When this line item existed before the import OR it was already imported in a previous batch
                        // (which is the same "it existed before" in this point in time), it will be deleted.
                        $deleteSupplierOrderLineItemIds[] = $existingSupplierOrderLineItem->getId();
                    }
                    // When the same product was already imported within this batch in a previous line, we need to
                    // purge any upsert/creation payloads for it. So the product is consistently not added regardless of
                    // if or when it was imported before.
                    $productUpdatePayloads = array_filter($productUpdatePayloads, fn(array $payload) => $payload['id'] !== $product->getId());
                    $supplierOrderLineItemUpsertPayloads = array_filter($supplierOrderLineItemUpsertPayloads, fn(array $payload) => $payload['productId'] !== $product->getId());
                    $productSupplierConfigurationUpsertPayloads = array_filter($productSupplierConfigurationUpsertPayloads, fn(array $payload) => $payload['productId'] !== $product->getId());
                    $supplierOrderLineItemCreationInputs = array_filter(
                        $supplierOrderLineItemCreationInputs,
                        fn(SupplierOrderLineItemPayloadCreationInput $input) => $input->getProductSupplierConfigurationId() !== $existingProductSupplierConfiguration?->getId(),
                    );

                    continue;
                }

                $productSupplierConfigurationId = $existingProductSupplierConfiguration?->getId() ?? Uuid::randomHex();
                if ($updateProductAndSupplierConfiguration) {
                    $productUpdatePayloads[] = $this->getProductPayload(
                        $product,
                        $normalizedRow,
                        $allManufacturers,
                        $importExportLogEntryCreationPayloads,
                        $importElement,
                    );
                }

                // The product supplier configuration will ohne be upserted if the import config is set, or if no
                // configuration existed before (new configurations will always be created if possible).
                if ($updateProductAndSupplierConfiguration || !$existingProductSupplierConfiguration) {
                    if (
                        !$existingProductSupplierConfiguration
                        && (
                            // The product already has product supplier assignment or one will be created for it
                            $product->getExtension('pickwareErpProductSupplierConfigurations')->count() > 0
                            || (count(array_filter(
                                $productSupplierConfigurationUpsertPayloads,
                                fn(array $payload) => $payload['productId'] === $product->getId(),
                            )) > 0)
                        )
                        && !$this->featureFlagService->isActive(MultipleSuppliersPerProductProductionFeatureFlag::NAME)
                    ) {
                        // A supplier configuration exists, but not for this supplier. So  a new one should be created
                        // here. The disabled feature flag does not allow this.
                        $importExportLogEntryCreationPayloads[] = $this->createImportLogEntryPayload(
                            $importElement,
                            SupplierOrderImportMessage::createNewProductSupplierConfigurationCannotBeCreatedBecauseFeatureFlagNotActiveError(
                                $product->getProductNumber(),
                            ),
                        );
                    } else {
                        // Creation of a new product supplier configuration is allowed, or update the existing one
                        $productSupplierConfigurationPayload = $this->getProductSupplierConfigurationPayload(
                            $product,
                            $supplierOrder,
                            $normalizedRow,
                            $existingProductSupplierConfiguration,
                        );
                        $productSupplierConfigurationPayload['id'] = $productSupplierConfigurationId;

                        if ($existingProductSupplierConfiguration) {
                            $this->addLogIfPurchasePriceChanged(
                                $productSupplierConfigurationPayload['purchasePrices'] ? $productSupplierConfigurationPayload['purchasePrices'][$supplierOrder->getCurrencyId()]['net'] : null,
                                $this->getPriceForCurrency(
                                    prices: $existingProductSupplierConfiguration->getPurchasePrices(),
                                    currencyId: $supplierOrder->getCurrencyId(),
                                    net: true,
                                ),
                                $importExportLogEntryCreationPayloads,
                                $product->getProductNumber(),
                                $supplierOrder,
                                $importElement,
                                $context,
                            );
                        } else {
                            $importExportLogEntryCreationPayloads[] = $this->createImportLogEntryPayload(
                                $importElement,
                                SupplierOrderImportMessage::createNewProductSupplierConfigurationCreatedInfo(
                                    $product->getProductNumber(),
                                    $supplierOrder->getSupplier()->getName(),
                                ),
                            );
                        }
                        $productSupplierConfigurationUpsertPayloads[] = $productSupplierConfigurationPayload;
                    }
                }

                if ($existingSupplierOrderLineItem) {
                    $orderLineItemUpdatePayload = $this->getOrderLineItemUpdatePayload(
                        $existingSupplierOrderLineItem->getId(),
                        $normalizedRow,
                        $product,
                        $supplierOrder,
                    );

                    // If the line item we're updating here was scheduled for deletion by a previous row, don't delete it
                    $deleteSupplierOrderLineItemIds = array_diff($deleteSupplierOrderLineItemIds, [$existingSupplierOrderLineItem->getId()]);
                    $newUnitPrice = $orderLineItemUpdatePayload['priceDefinition']->getPrice();
                    $supplierOrderLineItemUpsertPayloads[] = $orderLineItemUpdatePayload;
                } else {
                    $newUnitPrice = $this->getPurchasePriceWithFallbackChain(
                        $normalizedRow,
                        $product,
                        $supplierOrder,
                    );

                    $supplierOrderLineItemUpsertPayloads = array_filter(
                        $supplierOrderLineItemUpsertPayloads,
                        fn(array $payload) => $payload['productId'] !== $product->getId(),
                    );
                    if (!$existingProductSupplierConfiguration) {
                        $supplierOrderLineItemUpsertPayloads[] = array_merge(
                            $this->supplierOrderLineItemCreationService
                                ->createSupplierOrderLineItemPayloadWithoutProductSupplierConfiguration(
                                    $product->getId(),
                                    $normalizedRow['quantity'],
                                    $newUnitPrice,
                                    $supplierOrder->getId(),
                                    $context,
                                ),
                            $this->getAdditionalFieldsFromRow($normalizedRow),
                        );
                    } else {
                        $orderLineItemCreationInput = new SupplierOrderLineItemPayloadCreationInput(
                            productSupplierConfigurationId: $productSupplierConfigurationId,
                            quantity: $normalizedRow['quantity'],
                            supplierOrderId: $supplierOrder->getId(),
                            unitPrice: $newUnitPrice,
                            additionalFields: $this->getAdditionalFieldsFromRow($normalizedRow),
                        );
                        // Make sure that only the newest creation input is kept for this product
                        $supplierOrderLineItemCreationInputs = array_filter(
                            $supplierOrderLineItemCreationInputs,
                            fn(SupplierOrderLineItemPayloadCreationInput $input) => $input->getProductSupplierConfigurationId() !== $orderLineItemCreationInput->getProductSupplierConfigurationId(),
                        );
                        $supplierOrderLineItemCreationInputs[] = $orderLineItemCreationInput;
                    }
                }
                if (FloatComparator::equals($newUnitPrice, 0.00)) {
                    $formattedPrice = $this->currencyFormatter->formatCurrencyByLanguage(
                        0.00,
                        $supplierOrder->getCurrency()->getShortName(),
                        $context->getLanguageId(),
                        $context,
                    );

                    $importExportLogEntryCreationPayloads[] = $this->createImportLogEntryPayload(
                        $importElement,
                        SupplierOrderImportMessage::createFallbackPriceForLineItemWasUsedInfo(
                            $product->getProductNumber(),
                            $formattedPrice,
                        ),
                    );
                }
            }
        }

        $this->entityManager->runInTransactionWithRetry(
            function() use (
                $context,
                $productSupplierConfigurationUpsertPayloads,
                $productUpdatePayloads,
                $supplierOrderLineItemUpsertPayloads,
                $supplierOrderLineItemCreationInputs,
                $deleteSupplierOrderLineItemIds,
                $importExportLogEntryCreationPayloads,
                $supplierOrder,
            ): void {
                $this->entityManager->update(
                    ProductDefinition::class,
                    $productUpdatePayloads,
                    $context,
                );
                $this->entityManager->upsert(
                    ProductSupplierConfigurationDefinition::class,
                    $productSupplierConfigurationUpsertPayloads,
                    $context,
                );

                // For the supplier order line item creation service we need all product supplier configurations to
                // exist in the database. That is why we upsert the product supplier configurations first, then create
                // all the remaining supplier order line item payloads.
                $supplierOrderLineItemUpsertPayloads = array_merge(
                    $supplierOrderLineItemUpsertPayloads,
                    $this->supplierOrderLineItemCreationService
                        ->createSupplierOrderLineItemPayloads($supplierOrderLineItemCreationInputs, $context)
                        ->getPayloadsBySupplierId($supplierOrder->getSupplierId()),
                );
                $this->entityManager->upsert(
                    SupplierOrderLineItemDefinition::class,
                    $supplierOrderLineItemUpsertPayloads,
                    $context,
                );

                $this->entityManager->delete(
                    SupplierOrderLineItemDefinition::class,
                    $deleteSupplierOrderLineItemIds,
                    $context,
                );
                $this->entityManager->create(
                    ImportExportLogEntryDefinition::class,
                    $importExportLogEntryCreationPayloads,
                    $context,
                );
                $this->orderRecalculationService->recalculateSupplierOrders([$supplierOrder->getId()], $context);
            },
        );

        if ($importElements->count() < $this->batchSize) {
            return null;
        }
        $nextRowNumberToRead += $this->batchSize;

        return $nextRowNumberToRead;
    }

    public function getProductSupplierConfigurationPayload(
        ProductEntity $product,
        SupplierOrderEntity $supplierOrder,
        array $normalizedRow,
        ?ProductSupplierConfigurationEntity $existingProductSupplierConfiguration,
    ): array {
        // Keep existing purchase prices (e.g. in other currencies)
        $existingPurchasePrices = $existingProductSupplierConfiguration?->getPurchasePrices()?->reduce(fn(array $carry, Price $purchasePrice) => [
            $purchasePrice->getCurrencyId() => $purchasePrice->jsonSerialize(),
            ...$carry,
        ], []) ?? [];

        // Add purchase price for the current currency to the existing ones for update
        $newPurchasePrices = [
            ...$existingPurchasePrices,
            ...[
                $supplierOrder->getCurrencyId() => $this->createPriceFromFloat(
                    $this->getPurchasePriceWithFallbackChain(
                        $normalizedRow,
                        $product,
                        $supplierOrder,
                    ),
                    $product,
                    $supplierOrder,
                )->jsonSerialize(),
            ],
        ];

        $payload = [
            'id' => Uuid::randomHex(),
            'productId' => $product->getId(),
            'supplierId' => $supplierOrder->getSupplierId(),
            'purchasePrices' => $newPurchasePrices,
        ];
        if (!$this->isEmpty($normalizedRow, 'supplierProductNumber')) {
            $payload['supplierProductNumber'] = $normalizedRow['supplierProductNumber'];
        }
        if (!$this->isEmpty($normalizedRow, 'minPurchase')) {
            $payload['minPurchase'] = $normalizedRow['minPurchase'];
        }
        if (!$this->isEmpty($normalizedRow, 'purchaseSteps')) {
            $payload['purchaseSteps'] = $normalizedRow['purchaseSteps'];
        }

        return $payload;
    }

    public function getProductPayload(
        ProductEntity $product,
        mixed $normalizedRow,
        ImmutableCollection $manufacturers,
        array &$importLogEntries,
        ImportExportElementEntity $importElement,
    ): array {
        $payload = [
            'id' => $product->getId(),
        ];
        if (!$this->isEmpty($normalizedRow, 'ean')) {
            $payload['ean'] = $normalizedRow['ean'];
        }
        if (!$this->isEmpty($normalizedRow, 'manufacturerProductNumber')) {
            $payload['manufacturerNumber'] = $normalizedRow['manufacturerProductNumber'];
        }
        if (!$this->isEmpty($normalizedRow, 'manufacturerName')) {
            $manufacturer = $this->getManufacturerFromRow($normalizedRow, $manufacturers, $importLogEntries, $importElement);
            if ($manufacturer !== null) {
                $payload['manufacturerId'] = $manufacturer->getId();
            }
        }

        return $payload;
    }

    public function getOrderLineItemUpdatePayload(
        string $existingSupplierOrderLineItemId,
        mixed $normalizedRow,
        ProductEntity $product,
        SupplierOrderEntity $supplierOrder,
    ): array {
        $unitPrice = $this->getPurchasePriceFromRow($normalizedRow) ?? $this->getProductPurchasePrice(
            $product,
            $supplierOrder,
        );

        $payload = [
            'id' => $existingSupplierOrderLineItemId,
            'priceDefinition' => new QuantityPriceDefinition(
                price: $unitPrice ?? 0.0,
                taxRules: new TaxRuleCollection([new TaxRule($product->getTax()->getTaxRate(), 100.0)]),
                quantity: $normalizedRow['quantity'],
            ),
            ...$this->getAdditionalFieldsFromRow($normalizedRow),
        ];

        // For later matching between update and create payloads, we need to identify the product here, even though the
        // product cannot not change with this update
        $payload['productId'] = $product->getId();

        return $payload;
    }

    /**
     * @param array<string, mixed> $normalizedRow
     * @return array<string, mixed>
     */
    private function getAdditionalFieldsFromRow(array $normalizedRow): array
    {
        $optionalFields = [
            'expectedDeliveryDate',
            'actualDeliveryDate',
        ];
        $additionalFields = [];
        foreach ($optionalFields as $field) {
            if (array_key_exists($field, $normalizedRow)) {
                $additionalFields[$field] = $normalizedRow[$field] === '' ? null : $normalizedRow[$field];
            }
        }

        return $additionalFields;
    }

    private function getProducts(
        array $normalizedRows,
        string $supplierId,
        Context $context,
    ): ProductCollection {
        $productNumbersFromNormalizedRows = array_column($normalizedRows, 'productNumber');
        $supplierProductNumbersFromNormalizedRows = array_column($normalizedRows, 'supplierProductNumber');

        $criteria = (new Criteria())
            ->addFilter(new OrFilter([
                new EqualsAnyFilter('productNumber', $productNumbersFromNormalizedRows),
                new AndFilter([
                    new EqualsFilter('pickwareErpProductSupplierConfigurations.supplierId', $supplierId),
                    new EqualsAnyFilter('extensions.pickwareErpProductSupplierConfigurations.supplierProductNumber', $supplierProductNumbersFromNormalizedRows),
                ]),
            ]));
        /** @var ProductCollection $products */
        $products = $context->enableInheritance(fn(Context $inheritedContext) => $this->entityManager->findBy(
            ProductDefinition::class,
            $criteria,
            $inheritedContext,
            [
                'tax',
                'pickwareErpProductSupplierConfigurations',
            ],
        ));

        return $products;
    }

    private function getManufacturers(
        array $normalizedRows,
        Context $context,
    ): ProductManufacturerCollection {
        $manufacturerNamesFromNormalizedRows = array_filter(array_column($normalizedRows, 'manufacturerName'));
        if (count($manufacturerNamesFromNormalizedRows) === 0) {
            return new ProductManufacturerCollection();
        }

        /** @var ProductManufacturerCollection $manufacturers */
        $manufacturers = $this->entityManager->findBy(
            ProductManufacturerDefinition::class,
            ['name' => $manufacturerNamesFromNormalizedRows],
            $context,
        );

        return $manufacturers;
    }

    private function getManufacturerFromRow(
        array $normalizedRow,
        ImmutableCollection $manufacturers,
        array &$importLogEntries,
        ImportExportElementEntity $importElement,
    ): ?ProductManufacturerEntity {
        if ($this->isEmpty($normalizedRow, 'manufacturerName')) {
            return null;
        }
        $manufacturerName = $normalizedRow['manufacturerName'];
        $manufacturer = $manufacturers->first(
            fn(ProductManufacturerEntity $manufacturer) => $manufacturer->getName() === $manufacturerName,
        );
        if (!$manufacturer) {
            $importLogEntries[] = $this->createImportLogEntryPayload(
                $importElement,
                SupplierOrderImportMessage::createManufacturerNotFoundError($manufacturerName),
            );
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

    private function getPurchasePriceFromRow(array $normalizedRow): ?float
    {
        if (isset($normalizedRow['unitPrice']) && $normalizedRow['unitPrice'] !== '') {
            return $normalizedRow['unitPrice'];
        }

        if (isset($normalizedRow['totalPrice']) && $normalizedRow['totalPrice'] !== '') {
            return round($normalizedRow['totalPrice'] / $normalizedRow['quantity'], 2);
        }

        return null;
    }

    /**
     * Returns the purchase price for the product either from the product supplier configuration or the product itself.
     */
    private function getProductPurchasePrice(ProductEntity $product, SupplierOrderEntity $supplierOrder): ?float
    {
        $productSupplierConfiguration = $this->getProductSupplierConfiguration($product, $supplierOrder->getSupplierId());

        $priceFromProductSupplierConfiguration = doIf(
            $productSupplierConfiguration,
            fn(ProductSupplierConfigurationEntity $productSupplierConfiguration) => $this->getPriceForCurrency(
                $productSupplierConfiguration->getPurchasePrices(),
                $supplierOrder->getCurrencyId(),
                $this->isNetSupplierOrder($supplierOrder),
            ),
        );

        $priceFromProduct = $this->getPriceForCurrency(
            $product->getPurchasePrices(),
            $supplierOrder->getCurrencyId(),
            $this->isNetSupplierOrder($supplierOrder),
        );

        return $priceFromProductSupplierConfiguration ?? $priceFromProduct;
    }

    private function getPurchasePriceWithFallbackChain(
        array $normalizedRow,
        ProductEntity $product,
        SupplierOrderEntity $supplierOrder,
    ): float {
        return $this->getPurchasePriceFromRow($normalizedRow) ?? $this->getProductPurchasePrice($product, $supplierOrder) ?? 0.0;
    }

    private function getPriceForCurrency(?PriceCollection $prices, string $currencyId, bool $net): ?float
    {
        $price = $prices?->firstWhere(fn(Price $price) => $price->getCurrencyId() === $currencyId);

        return $net ? $price?->getNet() : $price?->getGross();
    }

    private function getProductSupplierConfiguration(ProductEntity $product, ?string $getSupplierId): ?ProductSupplierConfigurationEntity
    {
        return $product->getExtension('pickwareErpProductSupplierConfigurations')->firstWhere(
            fn(ProductSupplierConfigurationEntity $productSupplierConfiguration) => $productSupplierConfiguration->getSupplierId() === $getSupplierId,
        );
    }

    private function createImportLogEntryPayload(
        ImportExportElementEntity $importElement,
        SupplierOrderImportMessage $supplierOrderImportMessage,
    ): array {
        return [
            'importExportId' => $importElement->getImportExportId(),
            'logLevel' => $supplierOrderImportMessage->getLevel(),
            'rowNumber' => $importElement->getRowNumber(),
            'message' => ImportExportLogEntryMessage::fromTranslatedMessage($supplierOrderImportMessage),
        ];
    }

    private function isEmpty(array $data, string $key): bool
    {
        return !isset($data[$key]) || $data[$key] === '';
    }

    private function addLogIfPurchasePriceChanged(
        ?float $newPurchasePriceNet,
        ?float $oldPurchasePriceNet,
        array &$importLogEntries,
        string $productNumber,
        SupplierOrderEntity $supplierOrder,
        ImportExportElementEntity $importElement,
        Context $context,
    ): void {
        if ($newPurchasePriceNet === null) {
            return;
        }

        if ($oldPurchasePriceNet !== $newPurchasePriceNet) {
            $formattedPrice = $this->currencyFormatter->formatCurrencyByLanguage(
                $newPurchasePriceNet,
                $supplierOrder->getCurrency()->getShortName(),
                $context->getLanguageId(),
                $context,
            );

            $importLogEntries[] = $this->createImportLogEntryPayload(
                $importElement,
                SupplierOrderImportMessage::createPriceOfProductSupplierConfigurationUpdatedInfo(
                    $productNumber,
                    $supplierOrder->getSupplier()->getName(),
                    $formattedPrice,
                ),
            );
        }
    }

    /**
     * We "select" an existing product to add or update a corresponding line item in the supplier order. We select a
     * product by product number (unique selection) or supplier product number (non-unique! selection). This is why
     * multiple products can be returned here.
     */
    private function getProductsForImport(
        array $normalizedRow,
        ImmutableCollection $allProducts,
        SupplierOrderEntity $supplierOrder,
    ): array {
        $products = [];
        if (!$this->isEmpty($normalizedRow, 'productNumber')) {
            $product = $allProducts->first(fn(ProductEntity $product) => $product->getProductNumber() === $normalizedRow['productNumber']);

            $products[] = $product;
        } elseif (!$this->isEmpty($normalizedRow, 'supplierProductNumber')) {
            $products = $allProducts->filter(
                function(ProductEntity $product) use ($supplierOrder, $normalizedRow): bool {
                    /** @var ProductSupplierConfigurationCollection $productSupplierConfigurations */
                    $productSupplierConfigurations = $product->getExtension('pickwareErpProductSupplierConfigurations');
                    $productSupplierConfigurationForCurrentSupplier = $productSupplierConfigurations->firstWhere(
                        fn(ProductSupplierConfigurationEntity $productSupplierConfiguration) => $productSupplierConfiguration->getSupplierId() === $supplierOrder->getSupplierId()
                        && $productSupplierConfiguration->getSupplierProductNumber() === $normalizedRow['supplierProductNumber'],
                    );

                    return $productSupplierConfigurationForCurrentSupplier !== null;
                },
            )->asArray();
        }

        return array_filter($products);
    }

    private function createPriceFromFloat(float $price, ProductEntity $product, SupplierOrderEntity $supplierOrder): Price
    {
        if ($this->isNetSupplierOrder($supplierOrder)) {
            $unitPriceNet = $price;
            $unitPriceGross = $price * (1 + $product->getTax()->getTaxRate() / 100);
        } else {
            $unitPriceGross = $price;
            $unitPriceNet = $price / (1 + $product->getTax()->getTaxRate() / 100);
        }

        return new Price($supplierOrder->getCurrencyId(), $unitPriceNet, $unitPriceGross, linked: true);
    }

    private function isNetSupplierOrder(SupplierOrderEntity $supplierOrder): bool
    {
        return $supplierOrder->getTaxStatus() === CartPrice::TAX_STATE_NET;
    }
}
