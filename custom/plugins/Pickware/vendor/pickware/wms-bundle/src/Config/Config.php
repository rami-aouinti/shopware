<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlag;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\FeatureFlagBundle\FeatureFlagType;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\BatchFeatureService;
use Pickware\PickwareErpStarter\Batch\BatchManagementProdFeatureFlag;
use Pickware\PickwareErpStarter\Config\GlobalPluginConfig;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptAdditionalInformationProdFeatureFlag;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptService;
use Pickware\PickwareErpStarter\Order\Model\PickwareErpPickwareOrderLineItemDefinition;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyProductionFeatureFlag;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyService;
use Pickware\PickwareErpStarter\ReturnOrder\LegacyReturnOrderService;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\Supplier\SupplierService;
use Pickware\PickwareWms\Batch\FeatureFlags\ProductBatchesSupportingBbdDevelopmentFeatureFlag;
use Pickware\PickwareWms\Config\FeatureFlags\DisableInvoicePrintingInWmsAppProdFeatureFlag;
use Pickware\PickwareWms\DocumentPrintingConfig\Model\DocumentPrintingConfigDefinition;
use Pickware\PickwareWms\DocumentPrintingConfig\Model\DocumentPrintingConfigEntity;
use Pickware\PickwareWms\PickingProcess\FeatureFlags\SingleItemOrdersPickingProductionFeatureFlag;
use Pickware\PickwareWms\PickwareWmsBundle;
use Pickware\PickwareWms\ShippingProcess\FeatureFlags\ShippingProcessProductionFeatureFlag;
use Pickware\ProductSetBundle\FeatureFlag\ProductSetFeatureFlag;
use Pickware\ProductSetBundle\ProductSet\ProductSetService;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Config
{
    private const FEATURE_FLAG_ALWAYS_ALLOW_CANCELLATION_OF_SINGLE_PICKING_PROCESS = 'pickware-wms.feature.always-allow-cancellation-of-single-picking-process';
    private const FEATURE_FLAG_PICKING_PARTIALLY_REFUNDED_ORDERS = 'pickware-wms.feature.picking-partially-refunded-orders';
    private const FEATURE_FLAG_LOAD_DEFAULT_STOCK_LOCATION = 'pickware-mobile-apps.feature.load-default-stock-location';
    private const FEATURE_FLAG_NEW_SHIPMENT_BLUEPRINT_FORMAT = 'pickware-wms.feature.new-shipment-blueprint-format';
    private const FEATURE_FLAG_PICKING_PROFILE_FILTER_FOR_PICKING_PROCESSES_AND_DELIVERIES = 'pickware-wms.feature.picking-profile-filter-for-picking-processes-and-deliveries';
    private const FEATURE_FLAG_RETURN_ORDER_STOCKING_LIST = 'pickware-wms.feature.return-order-stocking-list';
    private const FEATURE_FLAG_PICKING_PROFILE_CONTAINING_ORDER_TRANSACTION_STATE_FILTER = 'pickware-wms.feature.picking-profile-filter-containing-order-transaction-state-filter';
    private const FEATURE_FLAG_SUPPLIER_PRODUCT_NUMBER_SEARCH = 'pickware-wms.feature.supplier-product-number-search';
    private const FEATURE_FLAG_DELIVERY_PARCELS = 'pickware-wms.feature.delivery-parcels';
    private const FEATURE_FLAG_PICKING_PROFILES = 'pickware-wms.feature.picking-profiles';
    private const FEATURE_FLAG_SHIPMENT_BLUEPRINT_WITH_COUNTED_PRODUCTS = 'pickware-wms.feature.shipment-blueprint-with-counted-products';
    private const FEATURE_FLAG_PRIORITIZED_SHIPPING_AND_PAYMENT_METHOD = 'pickware-wms.feature.prioritized-shipping-and-payment-methods';
    private const FEATURE_FLAG_GOODS_RECEIPT_WITH_CUSTOMER_SOURCES = 'pickware-wms.feature.goods-receipt-with-customer-sources';
    private const FEATURE_FLAG_CONTINUE_PICKING_PROCESS_IN_ANY_WAREHOUSE = 'pickware-wms.feature.continue-picking-process-in-any-warehouse';
    private const FEATURE_FLAG_PRODUCT_UPDATE_PERMISSION = 'pickware-wms.feature.product-update-permission';
    private const FEATURE_FLAG_STOCKING_GOODS_RECEIPT_COMPLETELY = 'pickware-wms.feature.stocking-goods-receipt-completely';
    private const FEATURE_FLAG_PICKING_PROPERTIES = 'pickware-mobile-apps.feature.picking-properties';
    private const FEATURE_FLAG_GOODS_RECEIPT_ADDITIONAL_INFORMATION = 'pickware-wms.feature.goods-receipt-additional-information';
    private const FEATURE_FLAG_PICKING_PROFILE_NUMBER_OF_PICKABLE_ORDERS = 'pickware-wms.feature.picking-profile-number-of-pickable-orders';
    private const FEATURE_FLAG_SHIPMENT_BLUEPRINT_FOR_SHIPPING_METHOD = 'pickware-wms.feature.shipment-blueprint-for-shipping-method';
    private const FEATURE_FLAG_CORRECT_VERSIONED_ENTITY_FILTERS = 'pickware-wms.feature.correct-versioned-entity-filters';
    private const FEATURE_FLAG_STOCKTAKE_COUNTING_PROCESS_ITEM_READ_PERMISSION = 'pickware-wms.feature.stocktake-counting-process-item-read-permission';
    private const FEATURE_FLAG_ORDER_DOCUMENT_NUMBER = 'pickware-mobile-apps.feature.order-document-number';
    private const FEATURE_FLAG_MULTIPLE_SUPPLIERS_PER_PRODUCT = 'pickware-wms.feature.multiple-suppliers-per-product';
    private const FEATURE_FLAG_INCLUDES_IN_CUSTOM_ENDPOINTS = 'pickware-mobile-apps.feature.includes-in-custom-endpoints';
    private const FEATURE_FLAG_DELIVERY_RESPONSE_FOR_STOCK_CONTAINER_CREATION_FOR_DELIVERY = 'pickware-wms.feature.delivery-response-for-stock-container-creation-for-delivery';
    private const FEATURE_FLAG_PICKING_PROFILE_POSITION_FIELD = 'pickware-wms.feature.picking-profile-position-field';
    private const FEATURE_FLAG_PRODUCT_SETS = 'pickware-mobile-apps.feature.product-sets';
    private const FEATURE_FLAG_EXTERNALLY_FULFILLED_QUANTITY = 'pickware-mobile-apps.feature.externally-fulfilled-quantity';
    private const FEATURE_FLAG_PICKING_PROCESS_ASSIGNMENT_TO_DEVICE = 'pickware-wms.feature.picking-process-assignment-to-device';
    private const FEATURE_FLAG_SHIPPING_PROCESS = 'pickware-wms.feature.shipping-process';
    private const FEATURE_FLAG_PRESERVED_PICKING_PROCESSES = 'pickware-wms.feature.preserved-picking-processes';
    private const FEATURE_FLAG_PICKING_ACLS = 'pickware-wms.feature.picking-acls';
    private const FEATURE_FLAG_PRODUCT_BATCHES = 'pickware-mobile-apps.feature.product-batches';
    private const FEATURE_FLAG_STOCK_CANCELLED_PICKING_PROCESSES_AND_DELIVERIES = 'pickware-wms.feature.stock-cancelled-picking-processes-and-deliveries';
    private const FEATURE_FLAG_SINGLE_ITEM_ORDERS_PICKING = 'pickware-wms.feature.single-item-orders-picking';
    private const FEATURE_FLAG_PICKING_PROCESS_RECEIPT = 'pickware-wms.feature.picking-process-receipt';
    private const FEATURE_FLAG_PRODUCT_BATCHES_SUPPORTING_BBD = 'pickware-wms.feature.product-batches-supporting-bbd';
    private const FEATURE_FLAG_PARCEL_TRACKING_CODE = 'pickware-wms.feature.parcel-tracking-code';

    /**
     * All return reasons with their respective localizations as defined by PickwareErpStarter. The sort order also
     * matches the order used by PickwareErpStarter, specifically sorting 'unknown' to the top.
     */
    private const SORTED_RETURN_REASONS = [
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_UNKNOWN,
            'localizedName' => [
                'de' => 'Unbekannt',
                'en' => 'Unknown',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_SIZE_TOO_LARGE,
            'localizedName' => [
                'de' => 'Artikel war zu groß',
                'en' => 'Item was too large',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_SIZE_TOO_SMALL,
            'localizedName' => [
                'de' => 'Artikel war zu klein',
                'en' => 'Item was too small',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_UNWANTED,
            'localizedName' => [
                'de' => 'Kunde hat seine Meinung geändert',
                'en' => 'Customer changed their mind',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_NOT_AS_DESCRIBED,
            'localizedName' => [
                'de' => 'Artikel war nicht wie beschrieben',
                'en' => 'Item was not as described',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_WRONG_ITEM,
            'localizedName' => [
                'de' => 'Falschen Artikel erhalten',
                'en' => 'Wrong item received',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_DEFECTIVE,
            'localizedName' => [
                'de' => 'Beschädigt oder defekt',
                'en' => 'Damaged or broken',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_STYLE,
            'localizedName' => [
                'de' => 'Stil',
                'en' => 'Style',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_COLOR,
            'localizedName' => [
                'de' => 'Farbe',
                'en' => 'Color',
            ],
        ],
        [
            'technicalName' => ReturnOrderLineItemDefinition::REASON_OTHER,
            'localizedName' => [
                'de' => 'Sonstiges',
                'en' => 'Other',
            ],
        ],
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly GlobalPluginConfig $erpStarterGlobalPluginConfig,
        private readonly FeatureFlagService $featureFlagService,
        private readonly GoodsReceiptService $goodsReceiptService,
        // The picking property service is optional to ensure backwards compatibility
        private readonly ?PickingPropertyService $pickingPropertyService,
        // The supplier service is optional to ensure backwards compatibility
        private readonly ?SupplierService $supplierService,
        // The product set service is optional to ensure backwards compatibility
        private readonly ?ProductSetService $productSetService,
        // The batch feature service is optional to ensure backwards compatibility
        private readonly ?BatchFeatureService $batchFeatureService,
    ) {}

    /**
     * Creates and returns a Pickware WMS App configuration. This is the plugin configuration as well as several other
     * settings/ids/configuration of the shop.
     */
    public function getAppConfig(Context $context): array
    {
        $documentPrintingConfiguration = $this->entityManager
            ->findAll(DocumentPrintingConfigDefinition::class, $context, ['documentType'])
            ->map(function(DocumentPrintingConfigEntity $documentPrintingConfig) {
                if (
                    $documentPrintingConfig->getDocumentType()->getTechnicalName() === InvoiceRenderer::TYPE
                    && $this->featureFlagService->isActive(DisableInvoicePrintingInWmsAppProdFeatureFlag::NAME)
                ) {
                    // Regardless of the actual value here, the app will always call the API to create order documents.
                    // The controller will create the documents anyway, because the value 0 is only relevant for the WMS App.
                    // The value 0 leads to the WMS App printing zero copies of the invoice.
                    $copies = 0;
                } else {
                    $copies = $documentPrintingConfig->getCopies();
                }

                return [
                    'shippingMethodId' => $documentPrintingConfig->getShippingMethodId(),
                    'documentTypeId' => $documentPrintingConfig->getDocumentTypeId(),
                    'copies' => $copies,
                ];
            });

        /** @var DocumentTypeCollection $documentTypes */
        $documentTypes = $this->entityManager->findAll(DocumentTypeDefinition::class, $context);
        /** @var CurrencyEntity $defaultCurrency */
        $defaultCurrency = $this->entityManager->getByPrimaryKey(CurrencyDefinition::class, Defaults::CURRENCY, $context);
        $stockMovementComments = $this->erpStarterGlobalPluginConfig->getDefaultStockMovementComments();
        $orderNumberPrefixCollectionEvent = new OrderNumberPrefixCollectionEvent($context);
        $this->eventDispatcher->dispatch($orderNumberPrefixCollectionEvent);

        return array_merge(
            [
                'defaultCurrency' => $defaultCurrency,
                'documentTypes' => $documentTypes,
                'stockMovementComments' => $stockMovementComments,
                'documentPrintingConfiguration' => array_values($documentPrintingConfiguration),
                'returnReasons' => self::SORTED_RETURN_REASONS,
                'orderNumberSearchPrefixes' => array_values(array_unique(
                    $orderNumberPrefixCollectionEvent->getOrderNumberPrefixes(),
                )),
                'featureFlags' => $this->getFeatureFlags(),
            ],
            $this->getPluginConfigurationValues(),
        );
    }

    private function getPluginConfigurationValues(): array
    {
        /** @var array $configuration */
        $configuration = $this->systemConfigService->get(PickwareWmsBundle::GLOBAL_PLUGIN_CONFIG_DOMAIN) ?? [];
        $shippingLabelCreationMode = $configuration['shippingLabelCreationMode'] ?? 'manual';
        $batchPickingDocumentCreationMode = $configuration['batchPickingDocumentCreationMode'] ?? 'manual';
        $deliveryShippingMode = $configuration['deliveryShippingMode'] ?? 'manual';
        $guidedPicking = $configuration['guidedPicking'] ?? true;

        return [
            'shippingLabelCreationMode' => $shippingLabelCreationMode,
            'batchPickingDocumentCreationMode' => $batchPickingDocumentCreationMode,
            'deliveryShippingMode' => $deliveryShippingMode,
            'automaticallyPrintGoodsReceiptNotes' => $configuration['automaticallyPrintGoodsReceiptNotes'] ?? false,
            'displayExpectedStockInStocktaking' => $configuration['displayExpectedStockInStocktaking'] ?? false,
            'guidedPicking' => $guidedPicking,
        ];
    }

    /**
     * @return array{string: bool}
     */
    private function getFeatureFlags(): array
    {
        $featureFlags = [
            self::FEATURE_FLAG_ALWAYS_ALLOW_CANCELLATION_OF_SINGLE_PICKING_PROCESS => true,
            self::FEATURE_FLAG_PICKING_PARTIALLY_REFUNDED_ORDERS => true,
            self::FEATURE_FLAG_LOAD_DEFAULT_STOCK_LOCATION => true,
            self::FEATURE_FLAG_NEW_SHIPMENT_BLUEPRINT_FORMAT => true,
            self::FEATURE_FLAG_PICKING_PROFILE_FILTER_FOR_PICKING_PROCESSES_AND_DELIVERIES => true,
            self::FEATURE_FLAG_RETURN_ORDER_STOCKING_LIST => method_exists(
                LegacyReturnOrderService::class,
                'generateReturnOrderStockingListDocument',
            ),
            self::FEATURE_FLAG_PICKING_PROFILE_CONTAINING_ORDER_TRANSACTION_STATE_FILTER => true,
            self::FEATURE_FLAG_SUPPLIER_PRODUCT_NUMBER_SEARCH => true,
            self::FEATURE_FLAG_DELIVERY_PARCELS => true,
            self::FEATURE_FLAG_PICKING_PROFILES => true,
            self::FEATURE_FLAG_SHIPMENT_BLUEPRINT_WITH_COUNTED_PRODUCTS => true,
            self::FEATURE_FLAG_PRIORITIZED_SHIPPING_AND_PAYMENT_METHOD => true,
            self::FEATURE_FLAG_GOODS_RECEIPT_WITH_CUSTOMER_SOURCES => (
                method_exists($this->goodsReceiptService, 'areGoodsReceiptsForCustomersAvailable')
                && $this->goodsReceiptService->areGoodsReceiptsForCustomersAvailable()
            ),
            self::FEATURE_FLAG_CONTINUE_PICKING_PROCESS_IN_ANY_WAREHOUSE => true,
            self::FEATURE_FLAG_PRODUCT_UPDATE_PERMISSION => true,
            self::FEATURE_FLAG_STOCKING_GOODS_RECEIPT_COMPLETELY => true,
            self::FEATURE_FLAG_PICKING_PROPERTIES => (
                $this->pickingPropertyService?->arePickingPropertiesAvailable()
                && class_exists(PickingPropertyProductionFeatureFlag::class)
                && $this->featureFlagService->isActive(PickingPropertyProductionFeatureFlag::NAME)
            ),
            self::FEATURE_FLAG_GOODS_RECEIPT_ADDITIONAL_INFORMATION => (
                method_exists($this->goodsReceiptService, 'areGoodsReceiptAdditionalInformationAvailable')
                && $this->goodsReceiptService->areGoodsReceiptAdditionalInformationAvailable()
                && class_exists(GoodsReceiptAdditionalInformationProdFeatureFlag::class)
                && $this->featureFlagService->isActive(GoodsReceiptAdditionalInformationProdFeatureFlag::NAME)
            ),
            self::FEATURE_FLAG_PICKING_PROFILE_NUMBER_OF_PICKABLE_ORDERS => true,
            self::FEATURE_FLAG_SHIPMENT_BLUEPRINT_FOR_SHIPPING_METHOD => true,
            self::FEATURE_FLAG_CORRECT_VERSIONED_ENTITY_FILTERS => true,
            self::FEATURE_FLAG_STOCKTAKE_COUNTING_PROCESS_ITEM_READ_PERMISSION => true,
            self::FEATURE_FLAG_ORDER_DOCUMENT_NUMBER => true,
            self::FEATURE_FLAG_MULTIPLE_SUPPLIERS_PER_PRODUCT => (
                $this->supplierService !== null
                && method_exists($this->supplierService, 'areMultipleSuppliersPerProductAvailable')
                && $this->supplierService->areMultipleSuppliersPerProductAvailable()
            ),
            self::FEATURE_FLAG_INCLUDES_IN_CUSTOM_ENDPOINTS => true,
            self::FEATURE_FLAG_DELIVERY_RESPONSE_FOR_STOCK_CONTAINER_CREATION_FOR_DELIVERY => true,
            self::FEATURE_FLAG_PICKING_PROFILE_POSITION_FIELD => true,
            // The second condition guarantees the feature flag is always enabled if the product-set-bundle is present.
            // We need the first condition to remain because we can't assume the latest product-set-bundle version (the
            // one with ProductSetService) is installed, which would otherwise disable the flag for those customers.
            self::FEATURE_FLAG_PRODUCT_SETS => (
                (
                    class_exists(ProductSetFeatureFlag::class)
                    && $this->featureFlagService->isActive(ProductSetFeatureFlag::NAME)
                )
                || (
                    $this->productSetService !== null
                    && $this->productSetService->areProductSetsAvailable()
                )
            ),
            self::FEATURE_FLAG_EXTERNALLY_FULFILLED_QUANTITY => class_exists(PickwareErpPickwareOrderLineItemDefinition::class),
            self::FEATURE_FLAG_PICKING_PROCESS_ASSIGNMENT_TO_DEVICE => true,
            self::FEATURE_FLAG_SHIPPING_PROCESS => $this->featureFlagService->isActive(ShippingProcessProductionFeatureFlag::NAME),
            self::FEATURE_FLAG_PRESERVED_PICKING_PROCESSES => true,
            self::FEATURE_FLAG_PICKING_ACLS => true,
            self::FEATURE_FLAG_PRODUCT_BATCHES => (
                $this->batchFeatureService !== null
                && $this->batchFeatureService->isBatchManagementAvailable()
                && class_exists(BatchManagementProdFeatureFlag::class)
                && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)
            ),
            self::FEATURE_FLAG_STOCK_CANCELLED_PICKING_PROCESSES_AND_DELIVERIES => true,
            self::FEATURE_FLAG_SINGLE_ITEM_ORDERS_PICKING => (
                $this->featureFlagService->isActive(SingleItemOrdersPickingProductionFeatureFlag::NAME)
                // Single item order picking mainly changes how deliveries are packed and shipped, so we only make this
                // available if the shipping process is also available
                && $this->featureFlagService->isActive(ShippingProcessProductionFeatureFlag::NAME)
            ),
            self::FEATURE_FLAG_PICKING_PROCESS_RECEIPT => true,
            self::FEATURE_FLAG_PRODUCT_BATCHES_SUPPORTING_BBD => (
                $this->batchFeatureService !== null
                && $this->batchFeatureService->isBatchManagementAvailable()
                && class_exists(BatchManagementProdFeatureFlag::class)
                && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)
                && $this->featureFlagService->isActive(ProductBatchesSupportingBbdDevelopmentFeatureFlag::NAME)
            ),
            self::FEATURE_FLAG_PARCEL_TRACKING_CODE => true,
        ];

        $productionFeatureFlags = ImmutableCollection::create($this->featureFlagService->getFeatureFlags()->getItems())
            ->filter(
                fn(FeatureFlag $featureFlag) => (
                    $featureFlag->getType() === FeatureFlagType::Production
                ),
            );

        return array_merge(
            $featureFlags,
            array_combine(
                $productionFeatureFlags->map(fn(FeatureFlag $featureFlag) => $featureFlag->getName())->asArray(),
                $productionFeatureFlags->map(fn(FeatureFlag $featureFlag) => $featureFlag->isActive())->asArray(),
            ),
        );
    }
}
