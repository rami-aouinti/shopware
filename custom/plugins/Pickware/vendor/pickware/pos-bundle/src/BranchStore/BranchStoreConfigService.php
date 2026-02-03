<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\BranchStore;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Order\Model\PickwareErpPickwareOrderLineItemDefinition;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyProductionFeatureFlag;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyService;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreDefinition;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreEntity;
use Pickware\PickwarePos\Installation\PickwarePosInstaller;
use Pickware\PickwarePos\PickwarePosBundle;
use Pickware\ProductSetBundle\FeatureFlag\ProductSetFeatureFlag;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class BranchStoreConfigService
{
    /**
     * This is _not_ the default branch store config, but fallback values that are used when no plugin config values are
     * set. In other words: the interpreted value of a plugin config field, when said field is empty.
     */
    private const FALLBACK_BRANCH_STORE_CONFIG_VALUES = [
        // General
        'posShippingMethod' => null,
        'posDefaultCustomer' => null,
        'posCashPaymentMethod' => null,
        'posCardPaymentMethods' => [],
        'posOtherPaymentMethods' => [],
        'posOpenPaymentMethods' => [],
        // POS receipt
        'posReceiptLogo' => null,
        'taxIdentificationNumber' => null,
        'posReceiptLeftAlignText' => null,
        'posReceiptCenterAlignText' => null,
        'posReceiptShowListPrices' => false,
        // POS
        'posAutomaticReceiptPrinting' => false,
        'posGroupProductVariants' => false,
        'posOversellingWarning' => false,
        'posDepositWithdrawalComments' => [],
        // Click & Collect
        'clickAndCollectShippingMethods' => [],
        'clickAndCollectPaymentMethod' => null,
        // Sales channel config
        'salesChannelId' => null,
        'currency' => null,
        'defaultCustomerGroup' => null,
        'fallbackCustomerGroup' => null,
        'customerFieldRequirements' => [
            'additionalAddressField1Required' => false,
            'additionalAddressField2Required' => false,
            'birthdayFieldRequired' => false,
            'phoneNumberFieldRequired' => false,
        ],
        'defaultLanguageId' => null,
        // Generic config
        'documentTypes' => [],
        'salutations' => [],
        'shippingMethods' => [],
        'defaultCurrency' => null,
        'featureFlags' => [],
        'technicalFeatureFlags' => [],
    ];

    /**
     * While the plugin config values only consist of entity IDs, the config created by this class contains the
     * respective entities. Hence, the config keys must be adapted accordingly.
     */
    private const PLUGIN_CONFIG_KEY_MAPPINGS = [
        'posShippingMethodId' => 'posShippingMethod',
        'posDefaultCustomerId' => 'posDefaultCustomer',
        'posCashPaymentMethodId' => 'posCashPaymentMethod',
        'posCardPaymentMethodIds' => 'posCardPaymentMethods',
        'posOtherPaymentMethodIds' => 'posOtherPaymentMethods',
        'posOpenPaymentMethodIds' => 'posOpenPaymentMethods',
        'posReceiptLogoMediaId' => 'posReceiptLogo',
        'clickAndCollectShippingMethodIds' => 'clickAndCollectShippingMethods',
        'clickAndCollectPaymentMethodId' => 'clickAndCollectPaymentMethod',
    ];

    private const FEATURE_FLAG_DOCUMENT_SENDING = 'pos_document_sending';
    private const FEATURE_FLAG_SALES_HISTORY = 'pos_sales_history';
    private const FEATURE_FLAG_CUSTOMER_CREATION = 'pos_customer_creation';
    private const FEATURE_FLAG_PROMOTION_REDEMPTION = 'pos_promotion_redemption';
    private const FEATURE_FLAG_RETURN_MODE = 'pos_return_mode';
    private const FEATURE_FLAG_VARIANT_GROUPING_SEARCH = 'pos_variant_grouping_search';
    private const FEATURE_FLAG_DEDICATED_RETURN_ORDER_COMPLETION_CONTROLLER = 'pos_dedicated_return_order_completion_controller';
    private const FEATURE_FLAG_STORE_BASE_PRICE_AS_ORIGINAL_PRICE = 'pos_store_base_price_as_original_price';
    private const FEATURE_FLAG_ACCESS_CONTROL = 'pos_access_control';
    private const TECHNICAL_FEATURE_FLAG_LOAD_DEFAULT_STOCK_LOCATION = 'pickware-mobile-apps.feature.load-default-stock-location';
    private const TECHNICAL_FEATURE_FLAG_READ_STOCK_MOVEMENT_PRIVILEGE = 'pickware-mobile-apps.feature.read-stock-movement-privilege';
    private const FEATURE_FLAG_ORDER_DOCUMENT_NUMBER = 'pickware-mobile-apps.feature.order-document-number';
    private const TECHNICAL_FEATURE_FLAG_CREATE_CASH_REGISTER_ENDPOINT = 'pickware-pos.feature.create-cash-register-endpoint';
    private const TECHNICAL_FEATURE_FLAG_INCLUDES_IN_CUSTOM_ENDPOINTS = 'pickware-mobile-apps.feature.includes-in-custom-endpoints';
    private const TECHNICAL_FEATURE_FLAG_PRODUCT_SETS = 'pickware-mobile-apps.feature.product-sets';
    private const TECHNICAL_FEATURE_FLAG_EXTERNALLY_FULFILLED_QUANTITY = 'pickware-mobile-apps.feature.externally-fulfilled-quantity';
    private const TECHNICAL_FEATURE_FLAG_AUTOMATIC_COUPON_REDEMPTION = 'pickware-pos.feature.automatic-coupon-redemption';
    private const FEATURE_FLAG_PICKING_PROPERTIES = 'pickware-mobile-apps.feature.picking-properties';

    private EntityManager $entityManager;
    private SystemConfigService $systemConfigService;
    private FeatureFlagService $featureFlagService;
    private readonly PickingPropertyService $pickingPropertyService;

    public function __construct(
        EntityManager $entityManager,
        SystemConfigService $systemConfigService,
        FeatureFlagService $featureFlagService,
        ?PickingPropertyService $pickingPropertyService,
    ) {
        $this->entityManager = $entityManager;
        $this->systemConfigService = $systemConfigService;
        $this->featureFlagService = $featureFlagService;
        $this->pickingPropertyService = $pickingPropertyService;
    }

    public function getBranchStoreConfig(string $branchStoreId, Context $context): array
    {
        /** @var BranchStoreEntity $branchStore */
        $branchStore = $this->entityManager->findByPrimaryKey(
            BranchStoreDefinition::class,
            $branchStoreId,
            $context,
            [
                'salesChannel.currency',
                'salesChannel.customerGroup',
            ],
        );

        if (!$branchStore) {
            throw BranchStoreException::createBranchStoreNotFoundError($branchStoreId);
        }

        $salesChannel = $branchStore->getSalesChannel();
        if (!$salesChannel) {
            throw BranchStoreException::branchStoreHasNoSalesChannel($branchStore);
        }

        return array_merge(
            self::FALLBACK_BRANCH_STORE_CONFIG_VALUES,
            $this->getPluginConfigValues($context, $salesChannel->getId()),
            $this->getSalesChannelConfigValues($salesChannel, $context),
            $this->getGenericConfigValues($context),
        );
    }

    private function getPluginConfigValues(Context $context, ?string $salesChannelId = null): array
    {
        $configuration = $this->systemConfigService->get(PickwarePosBundle::PLUGIN_CONFIG_DOMAIN, $salesChannelId) ?? [];
        $hydratedConfiguration = $this->hydrateEntityConfigValues($context, $configuration);

        // Only return the media url for the pos receipt logo
        if (isset($hydratedConfiguration['posReceiptLogo'])) {
            $hydratedConfiguration['posReceiptLogo'] = $hydratedConfiguration['posReceiptLogo']->getUrl();
        }

        // Split deposit/withdrawal comments into separate strings
        if (isset($hydratedConfiguration['posDepositWithdrawalComments'])) {
            $hydratedConfiguration['posDepositWithdrawalComments'] = array_values(array_filter(
                explode("\n", $hydratedConfiguration['posDepositWithdrawalComments']),
            ));
        }

        return $hydratedConfiguration;
    }

    private function hydrateEntityConfigValues(Context $context, array $configuration): array
    {
        $configurationKeyToSingleEntityDefinitionMapping = [
            'posShippingMethodId' => ShippingMethodDefinition::class,
            'posDefaultCustomerId' => CustomerDefinition::class,
            'posCashPaymentMethodId' => PaymentMethodDefinition::class,
            'posReceiptLogoMediaId' => MediaDefinition::class,
            'clickAndCollectPaymentMethodId' => PaymentMethodDefinition::class,
        ];
        $entityAssociationMapping = [
            CustomerDefinition::class => [
                'defaultShippingAddress.countryState',
                'defaultShippingAddress.country',
                'defaultBillingAddress.countryState',
                'defaultBillingAddress.country',
                'group',
            ],
        ];

        foreach ($configurationKeyToSingleEntityDefinitionMapping as $key => $entityDefinitionClass) {
            if (isset($configuration[$key])) {
                $posConfigKey = self::PLUGIN_CONFIG_KEY_MAPPINGS[$key] ?? $key;
                $configuration[$posConfigKey] = $this->entityManager->findByPrimaryKey(
                    $entityDefinitionClass,
                    $configuration[$key],
                    $context,
                    $entityAssociationMapping[$entityDefinitionClass] ?? [],
                );
                if ($posConfigKey !== $key) {
                    unset($configuration[$key]);
                }
            }
        }

        $configurationKeyToMultiEntityDefinitionMapping = [
            'posCardPaymentMethodIds' => PaymentMethodDefinition::class,
            'posOtherPaymentMethodIds' => PaymentMethodDefinition::class,
            'posOpenPaymentMethodIds' => PaymentMethodDefinition::class,
            'clickAndCollectShippingMethodIds' => ShippingMethodDefinition::class,
        ];

        foreach ($configurationKeyToMultiEntityDefinitionMapping as $key => $entityDefinitionClass) {
            if (isset($configuration[$key])) {
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsAnyFilter('id', $configuration[$key]));

                $posConfigKey = self::PLUGIN_CONFIG_KEY_MAPPINGS[$key] ?? $key;
                $configuration[$posConfigKey] = $this->entityManager->findBy(
                    $entityDefinitionClass,
                    $criteria,
                    $context,
                );
                if ($posConfigKey !== $key) {
                    unset($configuration[$key]);
                }
            }
        }

        return $configuration;
    }

    private function getSalesChannelConfigValues(SalesChannelEntity $salesChannel, Context $context): array
    {
        $config = ['salesChannelId' => $salesChannel->getId()];
        $config['currency'] = $salesChannel->getCurrency();
        $config['defaultCustomerGroup'] = $salesChannel->getCustomerGroup();

        $config['fallbackCustomerGroup'] = $this->entityManager->findByPrimaryKey(
            CustomerGroupDefinition::class,
            PickwarePosInstaller::CUSTOMER_GROUP_FALLBACK_ID,
            $context,
        );

        $config['customerFieldRequirements'] = [];
        $customerFieldRequirementsKeys = array_keys(
            self::FALLBACK_BRANCH_STORE_CONFIG_VALUES['customerFieldRequirements'],
        );
        foreach ($customerFieldRequirementsKeys as $key) {
            $config['customerFieldRequirements'][$key] = $this->systemConfigService->getBool(
                'core.loginRegistration.' . $key,
                $salesChannel->getId(),
            );
        }

        $config['defaultLanguageId'] = $salesChannel->getLanguageId();

        return $config;
    }

    private function getGenericConfigValues(Context $context): array
    {
        $technicalFeatureFlags = [
            self::FEATURE_FLAG_DEDICATED_RETURN_ORDER_COMPLETION_CONTROLLER,
            self::FEATURE_FLAG_STORE_BASE_PRICE_AS_ORIGINAL_PRICE,
            self::FEATURE_FLAG_ACCESS_CONTROL,
            self::TECHNICAL_FEATURE_FLAG_LOAD_DEFAULT_STOCK_LOCATION,
            self::TECHNICAL_FEATURE_FLAG_READ_STOCK_MOVEMENT_PRIVILEGE,
            self::FEATURE_FLAG_ORDER_DOCUMENT_NUMBER,
            self::TECHNICAL_FEATURE_FLAG_CREATE_CASH_REGISTER_ENDPOINT,
            self::TECHNICAL_FEATURE_FLAG_INCLUDES_IN_CUSTOM_ENDPOINTS,
            self::TECHNICAL_FEATURE_FLAG_AUTOMATIC_COUPON_REDEMPTION,
        ];

        if (class_exists(PickwareErpPickwareOrderLineItemDefinition::class)) {
            $technicalFeatureFlags[] = self::TECHNICAL_FEATURE_FLAG_EXTERNALLY_FULFILLED_QUANTITY;
        }

        if (
            class_exists(ProductSetFeatureFlag::class)
            && $this->featureFlagService->isActive(ProductSetFeatureFlag::NAME)
        ) {
            $technicalFeatureFlags[] = self::TECHNICAL_FEATURE_FLAG_PRODUCT_SETS;
        }

        $featureFlags = [
            self::FEATURE_FLAG_DOCUMENT_SENDING,
            self::FEATURE_FLAG_SALES_HISTORY,
            self::FEATURE_FLAG_CUSTOMER_CREATION,
            self::FEATURE_FLAG_PROMOTION_REDEMPTION,
            self::FEATURE_FLAG_RETURN_MODE,
            self::FEATURE_FLAG_VARIANT_GROUPING_SEARCH,
        ];

        if (
            $this->pickingPropertyService?->arePickingPropertiesAvailable()
            && class_exists(PickingPropertyProductionFeatureFlag::class)
            && $this->featureFlagService->isActive(PickingPropertyProductionFeatureFlag::NAME)
        ) {
            $featureFlags[] = self::FEATURE_FLAG_PICKING_PROPERTIES;
        }

        return [
            'documentTypes' => $this->entityManager->findAll(DocumentTypeDefinition::class, $context),
            'salutations' => $this->entityManager->findAll(SalutationDefinition::class, $context),
            'shippingMethods' => $this->entityManager->findAll(ShippingMethodDefinition::class, $context),
            'defaultCurrency' => $this->entityManager->findByPrimaryKey(
                CurrencyDefinition::class,
                Defaults::CURRENCY,
                $context,
            ),
            'featureFlags' => $featureFlags,
            'technicalFeatureFlags' => $technicalFeatureFlags,
        ];
    }
}
