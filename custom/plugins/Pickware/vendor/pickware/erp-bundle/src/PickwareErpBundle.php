<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter;

use Doctrine\DBAL\Connection;
use Pickware\AiBundle\PickwareAiBundle;
use Pickware\ApiErrorHandlingBundle\PickwareApiErrorHandlingBundle;
use Pickware\ApiVersioningBundle\PickwareApiVersioningBundle;
use Pickware\BundleInstaller\BundleInstaller;
use Pickware\ConfigBundle\PickwareConfigBundle;
use Pickware\DalBundle\PickwareDalBundle;
use Pickware\DebugBundle\PickwareDebugBundle;
use Pickware\DocumentBundle\PickwareDocumentBundle;
use Pickware\FeatureFlagBundle\PickwareFeatureFlagBundle;
use Pickware\IncompatibilityBundle\PickwareIncompatibilityBundle;
use Pickware\InstallationLibrary\BundleMigrationDropper;
use Pickware\InstallationLibrary\DependencyAwareTableDropper;
use Pickware\MoneyBundle\PickwareMoneyBundle;
use Pickware\PickwareErpStarter\Document\DependencyInjection\DocumentGeneratorCompilerPass;
use Pickware\PickwareErpStarter\Installation\PickwareErpInstaller;
use Pickware\PickwareErpStarter\Order\PickwareErpPickwareOrderLineItemInitializer;
use Pickware\PickwareErpStarter\Product\PickwareProductInitializer;
use Pickware\PickwareErpStarter\Stock\Indexer\ProductReservedStockIndexer;
use Pickware\PickwareErpStarter\Stock\ProductStockLocationMappingInitializer;
use Pickware\PickwareErpStarter\Stock\WarehouseStockInitializer;
use Pickware\ShopwareExtensionsBundle\PickwareShopwareExtensionsBundle;
use Pickware\ShopwarePlugins\ShopwareIntegrationTestPlugin\ShopwareIntegrationTestPlugin;
use Pickware\UpsellNudgingBundle\PickwareUpsellNudgingBundle;
use Pickware\ValidationBundle\PickwareValidationBundle;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use SwagMigrationAssistant\Migration\Writer\WriterInterface;
use SwagMigrationAssistant\SwagMigrationAssistant;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PickwareErpBundle extends Bundle
{
    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [
        PickwareDalBundle::class,
        PickwareDocumentBundle::class,
        PickwareMoneyBundle::class,
        PickwareApiErrorHandlingBundle::class,
        PickwareShopwareExtensionsBundle::class,
        PickwareValidationBundle::class,
        PickwareDebugBundle::class,
        PickwareConfigBundle::class,
        PickwareFeatureFlagBundle::class,
        PickwareIncompatibilityBundle::class,
        PickwareApiVersioningBundle::class,
        PickwareUpsellNudgingBundle::class,
        PickwareAiBundle::class,
    ];

    private static ?self $instance = null;
    private static bool $registered = false;
    private static bool $migrationsRegistered = false;

    public static function register(Collection $bundleCollection): void
    {
        if (self::$registered) {
            return;
        }

        $bundleCollection->add(self::getInstance());
        foreach (self::ADDITIONAL_BUNDLES as $bundle) {
            $bundle::register($bundleCollection);
        }

        self::$registered = true;
    }

    public static function registerMigrations(MigrationSource $migrationSource): void
    {
        if (self::$migrationsRegistered) {
            return;
        }
        $migrationsPath = self::getInstance()->getMigrationPath();
        $migrationNamespace = self::getInstance()->getMigrationNamespace();
        $migrationSource->addDirectory($migrationsPath, $migrationNamespace);

        self::$migrationsRegistered = true;

        foreach (self::ADDITIONAL_BUNDLES as $bundle) {
            $bundle::registerMigrations($migrationSource);
        }
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function build(ContainerBuilder $containerBuilder): void
    {
        parent::build($containerBuilder);

        $configLoader = new YamlFileLoader(
            $containerBuilder,
            new FileLocator(__DIR__),
            $containerBuilder->getParameter('kernel.environment'),
        );
        $configLoader->load('Resources/config/packages/monolog.yaml');

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__));
        $loader->load('AdministrationWindowProperty/DependencyInjection/twig-extension.xml');
        $loader->load('ImportExport/DependencyInjection/service.xml');
        $loader->load('ImportExport/ReadWrite/DependencyInjection/service.xml');
        $loader->load('Stock/DependencyInjection/import-export.xml');
        $loader->load('StockLocationSorting/DependencyInjection/service.xml');
        $loader->load('Stocktaking/DependencyInjection/import-export.xml');
        $loader->load('StockValuation/DependencyInjection/import-export.xml');
        $loader->load('PurchaseList/DependencyInjection/import-export.xml');
        $loader->load('Supplier/DependencyInjection/import-export.xml');
        $loader->load('SupplierOrder/DependencyInjection/import-export.xml');
        $loader->load('Warehouse/DependencyInjection/import-export.xml');

        // Register test services. Should never be loaded in production.
        if (in_array(ShopwareIntegrationTestPlugin::class, $containerBuilder->getParameter('kernel.bundles'), true)) {
            $loader->load('../test/TestEntityCreation/DependencyInjection/service.xml');
            $loader->load('Benchmarking/DependencyInjection/benchmark.xml');
        }

        $containerBuilder->addCompilerPass(new DocumentGeneratorCompilerPass());
        $containerBuilder->addCompilerPass(new class () implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setParameter('shopware.stock.enable_stock_management', false);
            }
        }, priority: PHP_INT_MIN);

        // Add SwagMigrationAssistant service decoration when the plugin is present.
        $activePlugins = $containerBuilder->getParameter('kernel.active_plugins');
        if (isset($activePlugins[SwagMigrationAssistant::class]) && interface_exists(WriterInterface::class)) {
            $loader->load('ShopwareMigration/DependencyInjection/service.xml');
        }
    }

    public function shutdown(): void
    {
        parent::shutdown();

        // Shopware may reboot the kernel under certain circumstances (e.g. plugin un-/installation) within a single
        // request. After the kernel was rebooted, our bundles have to be registered again.
        // We reset the registration flag when the kernel is shut down. This will cause the bundles to be registered
        // again in the (re)boot process.
        self::$registered = false;
    }

    public function install(InstallContext $installContext): void
    {
        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->install(self::ADDITIONAL_BUNDLES, $installContext);

        PickwareErpInstaller::initFromContainer($this->container)->install($installContext);
    }

    public function onAfterActivate(InstallContext $installContext): void
    {
        /** @var Connection $db */
        $db = $this->container->get(Connection::class);
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get('event_dispatcher');
        /** @var EntityIndexerRegistry $entityIndexerRegistry */
        $entityIndexerRegistry = $this->container->get('pickware_erp.entity_indexer_registry_public');

        // Subscribers take care of calculated values (e.g. stocks) and mandatory entity extensions (e.g. pickware
        // product) during the runtime of the shop while the plugin is installed.
        // Indexers repair the system when (for whatever reason) the subscribers fail or the information base changed
        // unknowingly. They should only be called manually.
        // To ensure that the system is in the correct state after the plugin is (1) installed for the first time or
        // (2) activated/reinstalled after it was deactivated/uninstalled for a period of time, we need to redo the
        // subscriber's job. But we _only upsert_ the mandatory entity extensions and _do not recalculate_ values for
        // performance reasons. Recalculating values in every update eventually overloads the message queue.
        (new WarehouseStockInitializer($db))->ensureProductWarehouseStocksExistsForAllProducts();
        (new PickwareProductInitializer($db, $eventDispatcher))->ensurePickwareProductsExistForAllProducts();
        (new PickwareErpPickwareOrderLineItemInitializer($db))->ensurePickwareErpPickwareOrderLineItemsExistForAllOrderLineItems();
        (new ProductStockLocationMappingInitializer($db))->ensureProductStockLocationMappingExistsForAllStocks();

        // When deactivating/uninstalling this plugin for a period of time while the shops is active, we need to
        // recalculate certain non-erp based indexer: reserved stock (based on orders). This is especially true when
        // installing the plugin for the first time.
        // We _do not_ recalculate stock as no stock movements are written while this plugin is deactivated/uninstalled
        // (or was never installed in the first place).
        $entityIndexerRegistry->sendIndexingMessage([
            ProductReservedStockIndexer::NAME,
        ]);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->container->get(Connection::class)->executeStatement('
            -- Migration1589893337AddWarehouseCustomFields.php
            DELETE FROM `custom_field_set_relation` WHERE `entity_name` = "pickware_erp_warehouse";

            -- Migration1599487702CreateProductReorderView.php
            DROP VIEW IF EXISTS `pickware_erp_product_reorder_view`;

            -- Migration1605002744CreateOrderPickabilityView.php
            DROP VIEW IF EXISTS `pickware_erp_order_pickability_view`;

            -- Migration1606220870CreateStockValuationView.php
            DROP VIEW IF EXISTS `pickware_erp_stock_valuation_view`;
        ');

        DependencyAwareTableDropper::createForContainer($this->container)->dropTables([
            'pickware_erp_address',
            'pickware_erp_analytics_aggregation_item_demand_planning',
            'pickware_erp_analytics_aggregation_session',
            'pickware_erp_analytics_aggregation',
            'pickware_erp_analytics_list_item_demand_planning',
            'pickware_erp_analytics_profile',
            'pickware_erp_analytics_report_config',
            'pickware_erp_analytics_report',
            'pickware_erp_analytics_session',
            'pickware_erp_batch_tag',
            'pickware_erp_batch_stock_mapping',
            'pickware_erp_batch_stock_movement_mapping',
            'pickware_erp_batch',
            'pickware_erp_bin_location',
            'pickware_erp_config',
            'pickware_erp_demand_planning_list_item',
            'pickware_erp_demand_planning_list_item',
            'pickware_erp_demand_planning_session',
            'pickware_erp_document_version',
            'pickware_erp_goods_receipt_document_mapping',
            'pickware_erp_goods_receipt_line_item',
            'pickware_erp_goods_receipt_tag',
            'pickware_erp_goods_receipt',
            'pickware_erp_import_element',
            'pickware_erp_import_export_element',
            'pickware_erp_import_export_log_entry',
            'pickware_erp_import_export_profile',
            'pickware_erp_import_export',
            'pickware_erp_location_type',
            'pickware_erp_message_queue_monitoring',
            'pickware_erp_order_log',
            'pickware_erp_order_pickability',
            'pickware_erp_order_stock_movement_process_mapping',
            'pickware_erp_picking_property_order_record_value',
            'pickware_erp_picking_property_order_record',
            'pickware_erp_picking_property_product_mapping',
            'pickware_erp_picking_property',
            'pickware_erp_pickware_order_line_item',
            'pickware_erp_pickware_product',
            'pickware_erp_product_configuration',
            'pickware_erp_product_sales_update_queue',
            'pickware_erp_product_stock_location_configuration',
            'pickware_erp_product_stock_location_mapping',
            'pickware_erp_product_supplier_configuration',
            'pickware_erp_product_warehouse_configuration',
            'pickware_erp_purchase_list_item',
            'pickware_erp_return_order_document_mapping',
            'pickware_erp_return_order_goods_receipt_mapping',
            'pickware_erp_return_order_line_item',
            'pickware_erp_return_order_refund',
            'pickware_erp_return_order_tag',
            'pickware_erp_return_order',
            'pickware_erp_special_stock_location',
            'pickware_erp_stock_container',
            'pickware_erp_stock_movement_process_type',
            'pickware_erp_stock_movement_process',
            'pickware_erp_stock_movement',
            'pickware_erp_stock_valuation_report_purchase',
            'pickware_erp_stock_valuation_report_row',
            'pickware_erp_stock_valuation_report',
            'pickware_erp_stock_valuation_temp_purchase',
            'pickware_erp_stock_valuation_temp_stock',
            'pickware_erp_stock',
            'pickware_erp_stocktaking_stocktake_counting_process_item',
            'pickware_erp_stocktaking_stocktake_counting_process',
            'pickware_erp_stocktaking_stocktake_product_summary',
            'pickware_erp_stocktaking_stocktake_snapshot_item',
            'pickware_erp_stocktaking_stocktake',
            'pickware_erp_supplier_order_goods_receipt_mapping',
            'pickware_erp_supplier_order_line_item',
            'pickware_erp_supplier_order_tag',
            'pickware_erp_supplier_order',
            'pickware_erp_supplier',
            'pickware_erp_warehouse_stock',
            'pickware_erp_warehouse',
        ]);

        $this->container->get(Connection::class)->executeStatement('
            ALTER TABLE `product`
                DROP COLUMN `pickwareErpPickingProperties`
        ');

        PickwareErpInstaller::initFromContainer($this->container)->uninstall($uninstallContext);

        BundleMigrationDropper::createForContainer($this->container)->dropMigrationsForBundle(__NAMESPACE__);
        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
