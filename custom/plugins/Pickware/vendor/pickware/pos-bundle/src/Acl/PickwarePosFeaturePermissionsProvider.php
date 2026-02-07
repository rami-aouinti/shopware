<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Acl;

use Pickware\AclBundle\FeaturePermission\FeatureCategory;
use Pickware\AclBundle\FeaturePermission\FeaturePermission;
use Pickware\AclBundle\FeaturePermission\FeaturePermissionsProvider;
use Pickware\DalBundle\Translation;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pickware_acl_bundle.feature_permissions_provider')]
class PickwarePosFeaturePermissionsProvider extends FeaturePermissionsProvider
{
    public const SPECIAL_POS_PRIVILEGE = 'pickware_pos';
    private const SPECIAL_BASIC_PRIVILEGE = 'pickware_pos_app_basic';
    private const BASIC_PRIVILEGES = [
        self::SPECIAL_POS_PRIVILEGE,
        'acl_role:read',
        'category:read',
        'country_state:read',
        'country:read',
        'currency:read',
        'custom_field_set_relation:read',
        'custom_field_set:read',
        'custom_field:read',
        'customer_address:create',
        'customer_address:read',
        'customer_address:update',
        'customer_group:read',
        'customer_recovery:create',
        'customer:create',
        'customer:read',
        'customer:update',
        'document_type:read',
        'document:read',
        'language:read',
        'locale:read',
        'log_entry:create',
        'media_thumbnail:read',
        'media:read',
        'message_queue_stats:read',
        'newsletter_recipient:create',
        'order_address:create',
        'order_address:read',
        'order_address:update',
        'order_customer:create',
        'order_customer:read',
        'order_customer:update',
        'order_delivery_position:create',
        'order_delivery_position:read',
        'order_delivery:create',
        'order_delivery:read',
        'order_delivery:update',
        'order_line_item:create',
        'order_line_item:read',
        'order_line_item:update',
        'order_tag:create',
        'order_tag:read',
        'order_tag:update',
        'order_transaction:create',
        'order_transaction:delete',
        'order_transaction:read',
        'order:create:discount',
        'order:create',
        'order:read',
        'order:update',
        'order.creator',
        'order.editor',
        'order.viewer',
        'payment_method:read',
        'pickware_erp_batch:read',
        'pickware_erp_batch_stock_mapping:create',
        'pickware_erp_batch_stock_mapping:delete',
        'pickware_erp_batch_stock_mapping:read',
        'pickware_erp_batch_stock_mapping:update',
        'pickware_erp_batch_stock_movement_mapping:create',
        'pickware_erp_batch_stock_movement_mapping:read',
        'pickware_erp_bin_location:read',
        'pickware_erp_location_type:read',
        'pickware_erp_order_pickability_view:read',
        'pickware_erp_picking_property:read',
        'pickware_erp_picking_property_order_record:create',
        'pickware_erp_picking_property_order_record_value:create',
        'pickware_erp_pickware_order_line_item:read',
        'pickware_erp_pickware_product:read',
        'pickware_erp_product_warehouse_configuration:read',
        'pickware_erp_return_order_line_item:create',
        'pickware_erp_return_order_line_item:read',
        'pickware_erp_return_order_refund:create',
        'pickware_erp_return_order:create',
        'pickware_erp_return_order:read',
        'pickware_erp_stock_container:update',
        'pickware_erp_stock_movement:read',
        'pickware_erp_stock:read',
        'pickware_erp_warehouse:read',
        'pickware_pos_address:create',
        'pickware_pos_address:read',
        'pickware_pos_address:update',
        'pickware_pos_branch_store:read',
        'pickware_pos_cash_point_closing_transaction_line_item:create',
        'pickware_pos_cash_point_closing_transaction_line_item:read',
        'pickware_pos_cash_point_closing_transaction_line_item:update',
        'pickware_pos_cash_point_closing_transaction:create',
        'pickware_pos_cash_point_closing_transaction:read',
        'pickware_pos_cash_point_closing_transaction:update',
        'pickware_pos_cash_point_closing:create',
        'pickware_pos_cash_point_closing:read',
        'pickware_pos_cash_point_closing:update',
        'pickware_pos_cash_register_fiskaly_configuration:read',
        'pickware_pos_cash_register:read',
        'pickware_pos_order_branch_store_mapping:create',
        'pickware_pos_order_branch_store_mapping:read',
        'pickware_pos_order_branch_store_mapping:update',
        'pickware_pos_order_line_item:create',
        'pickware_pos_order_line_item:read',
        'pickware_product_set_product_set_configuration:read',
        'pickware_product_set_product_set:read',
        'pickware_wms_delivery:update',
        'plugin:read',
        'product_export:read',
        'product_manufacturer:read',
        'product_media:read',
        'product_price:read',
        'product_stream:read',
        'product_visibility:read',
        'product:read',
        'promotion_cart_rule:read',
        'promotion_discount_prices:read',
        'promotion_discount_rule:read',
        'promotion_discount:read',
        'promotion_individual_code:read',
        'promotion_individual_code:update',
        'promotion_order_rule:read',
        'promotion_sales_channel:read',
        'promotion:read',
        'property_group_option:read',
        'property_group:read',
        'rule_condition:read',
        'rule:read',
        'sales_channel_analytics:read',
        'sales_channel_domain:read',
        'sales_channel_payment_method:read',
        'sales_channel_type:read',
        'sales_channel:read',
        'sales_channel.viewer',
        'salutation:read',
        'shipping_method:read',
        'snippet_set:read',
        'state_machine_history:create',
        'state_machine_history:read',
        'state_machine_state:read',
        'state_machine_state:update',
        'state_machine_transition:read',
        'state_machine:read',
        'system_config:read',
        'tag:create',
        'tag:read',
        'tax_rule_type:read',
        'tax_rule:read',
        'tax:read',
        'unit:read',
        'user:read',
    ];
    private const SPECIAL_PERFORM_SETUP_PRIVILEGE = 'pickware_pos_app_perform_setup';
    private const PERFORM_SETUP_PRIVILEGES = [
        'api_action_access-key_integration',
        'integration:create',
        'integration:read',
        'integration:update',
        'pickware_pos_address:create',
        'pickware_pos_branch_store:create',
        'pickware_pos_cash_register_fiskaly_configuration:create',
        'pickware_pos_cash_register_fiskaly_configuration:update',
        'pickware_pos_cash_register:create',
        'pickware_pos_cash_register:update',
        'sales_channel_country:create',
        'sales_channel_currency:create',
        'sales_channel_language:create',
        'sales_channel_payment_method:create',
        'sales_channel_shipping_method:create',
        'sales_channel:create',
        'sales_channel:update',
        'system_config:create',
    ];
    private const PICKWARE_POS_CATEGORY_TECHNICAL_NAME = 'pickware_pos';

    public function __construct()
    {
        $basicPermission = $this->getSpecialBasicFeaturePermission();

        parent::__construct([
            new FeatureCategory(
                technicalName: self::PICKWARE_POS_CATEGORY_TECHNICAL_NAME,
                translatedName: new Translation(
                    german: 'Pickware POS',
                    english: 'Pickware POS',
                ),
                featurePermissions: [
                    $basicPermission,
                    ...$this->getDefaultFeaturePermissions(),
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
                ],
            ),
        ]);
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
                technicalName: 'pickware_pos_app_cash_point_closing',
                translatedName: new Translation(
                    german: 'Kassenabschluss durchführen',
                    english: 'Perform cash point closing',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_pos_app_deposit_and_withdrawal',
                translatedName: new Translation(
                    german: 'Ein- und Auszahlung durchführen',
                    english: 'Perform deposit and withdrawal',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_pos_app_edit_product_price',
                translatedName: new Translation(
                    german: 'Produktpreis bearbeiten',
                    english: 'Edit product price',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_pos_app_grant_discount',
                translatedName: new Translation(
                    german: 'Rabatt gewähren',
                    english: 'Grant discount',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_pos_app_load_cart',
                translatedName: new Translation(
                    german: 'Warenkorb laden',
                    english: 'Load cart',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_pos_app_open_cash_drawer_manually',
                translatedName: new Translation(
                    german: 'Kassenschublade manuell öffnen',
                    english: 'Open cash drawer manually',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_pos_app_order_pickup',
                translatedName: new Translation(
                    german: 'Abholung durchführen',
                    english: 'Perform order pickups',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_pos_app_sales_history',
                translatedName: new Translation(
                    german: 'Verkaufshistorie öffnen',
                    english: 'Open sales history',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
            new FeaturePermission(
                technicalName: 'pickware_pos_app_view_purchase_prices',
                translatedName: new Translation(
                    german: 'Einkaufspreise einsehen',
                    english: 'View purchase prices',
                ),
                privileges: [],
                dependencies: [$basicPermission],
            ),
        ];
    }
}
