<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Acl;

use Pickware\AclBundle\FeaturePermission\FeatureCategory;
use Pickware\AclBundle\FeaturePermission\FeaturePermission;
use Pickware\AclBundle\FeaturePermission\FeaturePermissionsProvider;
use Pickware\DalBundle\Translation;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareWms\FeatureFlags\PrivilegeManagementProdFeatureFlag;
use Pickware\PickwareWms\Statistic\PickingStatisticsDashboardProdFeatureFlag;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: FeaturePermissionsProvider::DI_CONTAINER_TAG)]
class PickwareWmsFeaturePermissionsProvider extends FeaturePermissionsProvider
{
    private const LEGACY_FEATURE_PRIVILEGE_PERFORM_GOODS_RECEIPT = 'pickware_wms.perform_goods_receipt';
    private const LEGACY_FEATURE_PRIVILEGE_PERFORM_STOCK_MOVEMENTS = 'pickware_wms.perform_stock_movements';
    private const LEGACY_FEATURE_PRIVILEGE_PERFORM_STOCKTAKING = 'pickware_wms.perform_stocktaking';
    private const LEGACY_FEATURE_PRIVILEGE_SELECT_ANY_ORDER_FOR_PICKING = 'pickware_wms.select_any_order_for_picking';
    private const LEGACY_FEATURE_PRIVILEGE_TAKE_OVER_LOCKED_ORDER_FOR_PICKING = 'pickware_wms.take_over_locked_order_for_picking';
    public const LEGACY_FEATURE_PRIVILEGES = [
        self::LEGACY_FEATURE_PRIVILEGE_PERFORM_GOODS_RECEIPT,
        self::LEGACY_FEATURE_PRIVILEGE_PERFORM_STOCK_MOVEMENTS,
        self::LEGACY_FEATURE_PRIVILEGE_PERFORM_STOCKTAKING,
        self::LEGACY_FEATURE_PRIVILEGE_SELECT_ANY_ORDER_FOR_PICKING,
        self::LEGACY_FEATURE_PRIVILEGE_TAKE_OVER_LOCKED_ORDER_FOR_PICKING,
    ];
    public const SPECIAL_BASIC_PRIVILEGE = 'pickware_wms_app_basic';
    private const BASIC_PRIVILEGES = [
        self::SPECIAL_BASIC_PRIVILEGE,
        'acl_role:read',
        'country_state:read',
        'country:read',
        'currency:read',
        'customer_address:read',
        'customer_group:read',
        'customer:read',
        'document_type:read',
        'document:create',
        'document:read',
        'document:update',
        'language:read',
        'locale:read',
        'log_entry:create',
        'mail_template_type:update', // Sending an order status email might update the underlying template, which requires this privilege (see https://github.com/shopware/shopware/blob/e9ea279c4f3d8ca15b9eba8464dee0f960fafd2b/adr/2022-03-25-prevent-mail-updates.md)
        'media_thumbnail:read',
        'media:read',
        'message_queue_stats:read',
        'order_address:read',
        'order_customer:read',
        'order_delivery_position:read',
        'order_delivery:create',
        'order_delivery:read',
        'order_line_item:read',
        'order_transaction:read',
        'order:read',
        'payment_method:read',
        'pickware_document_type:read',
        'pickware_document:create',
        'pickware_document:read',
        'pickware_document:update',
        'pickware_erp_batch:create',
        'pickware_erp_batch:read',
        'pickware_erp_batch:update',
        'pickware_erp_batch_stock_mapping:create',
        'pickware_erp_batch_stock_mapping:delete',
        'pickware_erp_batch_stock_mapping:read',
        'pickware_erp_batch_stock_mapping:update',
        'pickware_erp_batch_stock_movement_mapping:create',
        'pickware_erp_batch_stock_movement_mapping:read',
        'pickware_erp_bin_location:read',
        'pickware_erp_goods_receipt_document_mapping:create',
        'pickware_erp_goods_receipt_document_mapping:delete',
        'pickware_erp_goods_receipt_document_mapping:read',
        'pickware_erp_goods_receipt_document_mapping:update',
        'pickware_erp_goods_receipt_line_item:create',
        'pickware_erp_goods_receipt_line_item:delete',
        'pickware_erp_goods_receipt_line_item:read',
        'pickware_erp_goods_receipt_line_item:update',
        'pickware_erp_goods_receipt:create',
        'pickware_erp_goods_receipt:read',
        'pickware_erp_goods_receipt:update',
        'pickware_erp_location_type:read',
        'pickware_erp_order_pickability:read',
        'pickware_erp_picking_property_order_record:create',
        'pickware_erp_picking_property_order_record_value:create',
        'pickware_erp_picking_property:read',
        'pickware_erp_pickware_order_line_item:read',
        'pickware_erp_pickware_product:read',
        'pickware_erp_product_supplier_configuration:read',
        'pickware_erp_product_warehouse_configuration:read',
        'pickware_erp_return_order:create',
        'pickware_erp_return_order:read',
        'pickware_erp_return_order:update',
        'pickware_erp_return_order_goods_receipt_mapping:create',
        'pickware_erp_return_order_goods_receipt_mapping:delete',
        'pickware_erp_return_order_goods_receipt_mapping:read',
        'pickware_erp_return_order_goods_receipt_mapping:update',
        'pickware_erp_return_order_line_item:create',
        'pickware_erp_return_order_line_item:delete',
        'pickware_erp_return_order_line_item:read',
        'pickware_erp_return_order_line_item:update',
        'pickware_erp_return_order_refund:create',
        'pickware_erp_return_order_refund:update',
        'pickware_erp_stock_container:create',
        'pickware_erp_stock_container:delete',
        'pickware_erp_stock_container:read',
        'pickware_erp_stock_container:update',
        'pickware_erp_stock_movement:read',
        'pickware_erp_stock:read',
        'pickware_erp_stocktaking_stocktake_counting_process_item:create',
        'pickware_erp_stocktaking_stocktake_counting_process_item:delete',
        'pickware_erp_stocktaking_stocktake_counting_process_item:read',
        'pickware_erp_stocktaking_stocktake_counting_process:create',
        'pickware_erp_stocktaking_stocktake_counting_process:delete',
        'pickware_erp_stocktaking_stocktake_counting_process:read',
        'pickware_erp_stocktaking_stocktake:read',
        'pickware_erp_supplier_order_goods_receipt_mapping:create',
        'pickware_erp_supplier_order_line_item:read',
        'pickware_erp_supplier_order:read',
        'pickware_erp_supplier:read',
        'pickware_erp_warehouse:read',
        'pickware_product_set_product_set_configuration:read',
        'pickware_product_set_product_set:read',
        'pickware_shipping_carrier:read',
        'pickware_shipping_document_shipment_mapping:read',
        'pickware_shipping_document_tracking_code_mapping:read',
        'pickware_shipping_shipment_order_mapping:read',
        'pickware_shipping_shipment:read',
        'pickware_shipping_shipping_method_config:read',
        'pickware_shipping_tracking_code:read',
        'pickware_shopware_extensions_order_configuration:read',
        'pickware_wms_delivery_document_mapping:create',
        'pickware_wms_delivery_document_mapping:read',
        'pickware_wms_delivery_document_mapping:update',
        'pickware_wms_delivery_line_item:create',
        'pickware_wms_delivery_line_item:delete',
        'pickware_wms_delivery_line_item:read',
        'pickware_wms_delivery_line_item:update',
        'pickware_wms_delivery_order_document_mapping:create',
        'pickware_wms_delivery_order_document_mapping:read',
        'pickware_wms_delivery_order_document_mapping:update',
        'pickware_wms_delivery_parcel:create',
        'pickware_wms_delivery_parcel:delete',
        'pickware_wms_delivery_parcel:read',
        'pickware_wms_delivery_parcel:update',
        'pickware_wms_delivery_parcel_tracking_code:create',
        'pickware_wms_delivery_parcel_tracking_code:read',
        'pickware_wms_delivery_parcel_tracking_code:update',
        'pickware_wms_delivery:create',
        'pickware_wms_delivery:read',
        'pickware_wms_delivery:update',
        'pickware_wms_device:create',
        'pickware_wms_device:read',
        'pickware_wms_device:update',
        'pickware_wms_document_printing_config:read',
        'pickware_wms_picking_process_reserved_item:create',
        'pickware_wms_picking_process_reserved_item:delete',
        'pickware_wms_picking_process_reserved_item:read',
        'pickware_wms_picking_process_reserved_item:update',
        'pickware_wms_picking_process:create',
        'pickware_wms_picking_process:read',
        'pickware_wms_picking_process:update',
        'pickware_wms_picking_profile:read',
        'pickware_wms_picking_profile_prioritized_shipping_method:read',
        'pickware_wms_picking_profile_prioritized_payment_method:read',
        'pickware_wms_picking_property_delivery_record:create',
        'pickware_wms_picking_property_delivery_record:read',
        'pickware_wms_picking_property_delivery_record:delete',
        'pickware_wms_picking_property_delivery_record_value:create',
        'pickware_wms_picking_property_delivery_record_value:read',
        'pickware_wms_picking_property_delivery_record_value:delete',
        'pickware_wms_shipping_method_config:read',
        'pickware_wms_shipping_process:create',
        'pickware_wms_shipping_process:read',
        'pickware_wms_shipping_process:update',
        'pickware_wms_stocking_process_line_item:create',
        'pickware_wms_stocking_process_line_item:delete',
        'pickware_wms_stocking_process_line_item:read',
        'pickware_wms_stocking_process_line_item:update',
        'pickware_wms_stocking_process_source:create',
        'pickware_wms_stocking_process_source:delete',
        'pickware_wms_stocking_process_source:read',
        'pickware_wms_stocking_process_source:update',
        'pickware_wms_stocking_process:create',
        'pickware_wms_stocking_process:delete',
        'pickware_wms_stocking_process:read',
        'pickware_wms_stocking_process:update',
        'plugin:read',
        'product_manufacturer:read',
        'product_media:read',
        'product_price:read',
        'product:read',
        'product:update',
        'property_group_option:read',
        'property_group:read',
        'rule_condition:read',
        'rule:read',
        'sales_channel:read',
        'salutation:read',
        'shipping_method:read',
        'state_machine:read',
        'state_machine_history:create',
        'state_machine_history:update',
        'state_machine_state:read',
        'tag:read',
        'tax_rule_type:read',
        'tax_rule:read',
        'tax:read',
        'unit:read',
        'user:read',
    ];
    private const SPECIAL_PERFORM_SETUP_PRIVILEGE = 'pickware_wms_app_perform_setup';
    private const PERFORM_SETUP_PRIVILEGES = [
        'api_action_access-key_integration',
        'integration:create',
        'integration:read',
        'integration:update',
    ];
    private const SPECIAL_STOCK_GOODS_RECEIPT_COMPLETELY_PRIVILEGE = 'pickware_wms_app_stock_goods_receipt_completely';
    private const SPECIAL_VIEW_SALES_PRICE_PRIVILEGE = 'pickware_wms_app_view_sales_price';
    private const SPECIAL_VIEW_PURCHASE_PRICE_PRIVILEGE = 'pickware_wms_app_view_purchase_price';
    private const SPECIAL_CONFIRM_ALL_PRODUCT_SET_ELEMENTS_PRIVILEGE = 'pickware_wms_app_confirm_all_product_set_elements';
    private const PICKWARE_WMS_CATEGORY_TECHNICAL_NAME = 'pickware_wms';
    private const PICKWARE_WMS_REPORTS_CATEGORY_TECHNICAL_NAME = 'pickware_wms_pickware_reports';
    public const PICKING_DASHBOARD_PRIVILEGE = 'pickware_wms_view_picking_reports_dashboard';

    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
    ) {
        $featureCategories = [];

        if ($this->featureFlagService->isActive(PrivilegeManagementProdFeatureFlag::NAME)) {
            $basicPermission = $this->getSpecialBasicFeaturePermission();
            $featureCategories[] = new FeatureCategory(
                technicalName: self::PICKWARE_WMS_CATEGORY_TECHNICAL_NAME,
                translatedName: new Translation(
                    german: 'Pickware WMS',
                    english: 'Pickware WMS',
                ),
                featurePermissions: [
                    $basicPermission,
                    ...$this->getDefaultFeaturePermissions(),
                    ...$this->getSpecialFeaturePermissions(),
                ],
            );
        }

        if (
            $this->featureFlagService->isActive(PickingStatisticsDashboardProdFeatureFlag::NAME)
        ) {
            $featureCategories[] = new FeatureCategory(
                technicalName: self::PICKWARE_WMS_REPORTS_CATEGORY_TECHNICAL_NAME,
                translatedName: new Translation(
                    german: 'Pickware Auswertungen',
                    english: 'Pickware Reports',
                ),
                featurePermissions: [
                    new FeaturePermission(
                        technicalName: self::PICKING_DASHBOARD_PRIVILEGE,
                        translatedName: new Translation(
                            german: 'Picking Dashboard',
                            english: 'Picking Dashboard',
                        ),
                        privileges: [self::PICKING_DASHBOARD_PRIVILEGE],
                    ),
                ],
            );
        }

        parent::__construct($featureCategories);
    }

    /**
     * @return FeaturePermission[]
     */
    public function getSpecialFeaturePermissions(): array
    {
        $basicPermission = $this->getSpecialBasicFeaturePermission();

        return [
            new FeaturePermission(
                technicalName: self::SPECIAL_PERFORM_SETUP_PRIVILEGE,
                translatedName: new Translation(
                    // The names are prefixed with one non-breaking space. Shopware sorts the privileges
                    // alphabetically, but we want the setup feature permissions to always be the second item,
                    // which is why we add the non-breaking spaces.
                    german: ' Einrichtung durchführen',
                    english: ' Perform setup',
                ),
                privileges: self::PERFORM_SETUP_PRIVILEGES,
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: self::SPECIAL_STOCK_GOODS_RECEIPT_COMPLETELY_PRIVILEGE,
                translatedName: new Translation(
                    german: 'Wareneingang komplett einlagern',
                    english: 'Stock goods receipt completely',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: self::SPECIAL_CONFIRM_ALL_PRODUCT_SET_ELEMENTS_PRIVILEGE,
                translatedName: new Translation(
                    german: 'Alle Produkte einer Stückliste auf einmal bestätigen',
                    english: 'Confirm all products contained in a product set at once',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
        ];
    }

    public function getSpecialBasicFeaturePermission(): FeaturePermission
    {
        return new FeaturePermission(
            technicalName: self::SPECIAL_BASIC_PRIVILEGE,
            translatedName: new Translation(
                // The names are prefixed with two non-breaking spaces. Shopware sorts the privileges alphabetically,
                // but we want the basic feature permissions to always be the first item, which is why we add the
                // non-breaking spaces.
                german: '  Grundberechtigungen',
                english: '  Basic privileges',
            ),
            privileges: self::BASIC_PRIVILEGES,
        );
    }

    public function getDefaultFeaturePermissions(): array
    {
        $basicPermission = $this->getSpecialBasicFeaturePermission();

        return [
            new FeaturePermission(
                technicalName: 'pickware_wms_app_perform_goods_receipt',
                translatedName: new Translation(
                    german: 'Wareneingang erfassen',
                    english: 'Perform goods receipt',
                ),
                // Add the legacy privilege used by older version of the WMS app to ensure backwards compatibility
                privileges: [self::LEGACY_FEATURE_PRIVILEGE_PERFORM_GOODS_RECEIPT],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_wms_app_perform_stock_movements',
                translatedName: new Translation(
                    german: 'Warenbewegungen durchführen',
                    english: 'Perform stock movements',
                ),
                // Add the legacy privilege used by older version of the WMS app to ensure backwards compatibility
                privileges: [self::LEGACY_FEATURE_PRIVILEGE_PERFORM_STOCK_MOVEMENTS],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_wms_app_perform_stocktaking',
                translatedName: new Translation(
                    german: 'Inventur durchführen',
                    english: 'Perform stocktaking',
                ),
                // Add the legacy privilege used by older version of the WMS app to ensure backwards compatibility
                privileges: [self::LEGACY_FEATURE_PRIVILEGE_PERFORM_STOCKTAKING],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_wms_app_select_any_order_for_picking',
                translatedName: new Translation(
                    german: 'Beliebige Bestellungen kommissionieren',
                    english: 'Select any order for picking',
                ),
                // Add the legacy privilege used by older version of the WMS app to ensure backwards compatibility
                privileges: [self::LEGACY_FEATURE_PRIVILEGE_SELECT_ANY_ORDER_FOR_PICKING],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_wms_app_take_over_locked_order_for_picking',
                translatedName: new Translation(
                    german: 'Kommissionierung von anderem Benutzer übernehmen',
                    english: 'Take over picking from different user',
                ),
                // Add the legacy privilege used by older version of the WMS app to ensure backwards compatibility
                privileges: [self::LEGACY_FEATURE_PRIVILEGE_TAKE_OVER_LOCKED_ORDER_FOR_PICKING],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: self::SPECIAL_VIEW_SALES_PRICE_PRIVILEGE,
                translatedName: new Translation(
                    german: 'Verkaufspreise einsehen',
                    english: 'View sales prices',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: self::SPECIAL_VIEW_PURCHASE_PRICE_PRIVILEGE,
                translatedName: new Translation(
                    german: 'Einkaufspreise einsehen',
                    english: 'View purchase prices',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_wms_app_cancel_picking_process',
                translatedName: new Translation(
                    german: 'Kommissionierung abbrechen',
                    english: 'Cancel picking process',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_wms_app_perform_partial_delivery',
                translatedName: new Translation(
                    german: 'Teillieferung durchführen',
                    english: 'Perform partial delivery',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
        ];
    }
}
