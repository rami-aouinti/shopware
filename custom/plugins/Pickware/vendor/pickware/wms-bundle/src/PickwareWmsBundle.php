<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms;

use Pickware\AclBundle\PickwareAclBundle;
use Pickware\ApiErrorHandlingBundle\PickwareApiErrorHandlingBundle;
use Pickware\ApiVersioningBundle\PickwareApiVersioningBundle;
use Pickware\BundleInstaller\BundleInstaller;
use Pickware\DalBundle\PickwareDalBundle;
use Pickware\DocumentBundle\PickwareDocumentBundle;
use Pickware\InstallationLibrary\BundleMigrationDropper;
use Pickware\InstallationLibrary\DependencyAwareTableDropper;
use Pickware\LockingBundle\PickwareLockingBundle;
use Pickware\MobileAppAuthBundle\PickwareMobileAppAuthBundle;
use Pickware\PickwareErpStarter\PickwareErpBundle;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\AbstractBatchPreservingBinLocationProviderFactory;
use Pickware\PickwareWms\Installation\PickwareWmsInstaller;
use Pickware\ShippingBundle\PickwareShippingBundle;
use Pickware\ShopwareExtensionsBundle\PickwareShopwareExtensionsBundle;
use Pickware\ShopwarePlugins\ShopwareIntegrationTestPlugin\ShopwareIntegrationTestPlugin;
use Pickware\UpsellNudgingBundle\PickwareUpsellNudgingBundle;
use Pickware\ValidationBundle\PickwareValidationBundle;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class PickwareWmsBundle extends Bundle
{
    public const GLOBAL_PLUGIN_CONFIG_DOMAIN = 'PickwareWmsBundle.global-plugin-config';

    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [
        PickwareAclBundle::class,
        PickwareApiErrorHandlingBundle::class,
        PickwareApiVersioningBundle::class,
        PickwareDalBundle::class,
        PickwareDocumentBundle::class,
        PickwareErpBundle::class,
        PickwareLockingBundle::class,
        PickwareMobileAppAuthBundle::class,
        PickwareShippingBundle::class,
        PickwareShopwareExtensionsBundle::class,
        PickwareValidationBundle::class,
        PickwareUpsellNudgingBundle::class,
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

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__));
        $loader->load('DemodataGeneration/DependencyInjection/command.xml');
        $loader->load('Picking/DependencyInjection/service.xml');
        $loader->load('PickingProcess/DependencyInjection/service.xml');
        $loader->load('PickingProfile/DependencyInjection/service.xml');
        $loader->load('Statistic/DependencyInjection/import-export.xml');
        $loader->load('Stocking/DependencyInjection/service.xml');
        if (class_exists(AbstractBatchPreservingBinLocationProviderFactory::class)) {
            $loader->load('Stocking/DependencyInjection/batch_service.xml');
        }

        // Register test services. Should never be loaded in production.
        if (in_array(ShopwareIntegrationTestPlugin::class, $containerBuilder->getParameter('kernel.bundles'), true)) {
            $loader->load('../test/TestEntityCreation/DependencyInjection/service.xml');
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

        PickwareWmsInstaller::initFromContainer($this->container)->install($installContext);
    }

    public function onAfterActivate(InstallContext $installContext): void
    {
        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->onAfterActivate(self::ADDITIONAL_BUNDLES, $installContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        DependencyAwareTableDropper::createForContainer($this->container)->dropTables([
            'pickware_wms_delivery_document_mapping',
            'pickware_wms_delivery_lifecycle_event',
            'pickware_wms_delivery_lifecycle_event_user_role',
            'pickware_wms_delivery_line_item',
            'pickware_wms_delivery_order_document_mapping',
            'pickware_wms_delivery_tracking_code',
            'pickware_wms_delivery_parcel_tracking_code',
            'pickware_wms_delivery_parcel',
            'pickware_wms_delivery',
            'pickware_wms_device',
            'pickware_wms_document_printing_config',
            'pickware_wms_order',
            'pickware_wms_pick_event',
            'pickware_wms_pick_event_user_role',
            'pickware_wms_picking_process_document_mapping',
            'pickware_wms_picking_process_lifecycle_event',
            'pickware_wms_picking_process_lifecycle_event_user_role',
            'pickware_wms_picking_process_order_document_mapping',
            'pickware_wms_picking_process_reserved_item',
            'pickware_wms_picking_process_tracking_code',
            'pickware_wms_picking_process_log',
            'pickware_wms_picking_process',
            'pickware_wms_picking_profile',
            'pickware_wms_picking_profile_prioritized_shipping_method',
            'pickware_wms_picking_profile_prioritized_payment_method',
            'pickware_wms_picking_property_delivery_record',
            'pickware_wms_picking_property_delivery_record_value',
            'pickware_wms_shipping_process',
            'pickware_wms_stocking_process_line_item',
            'pickware_wms_stocking_process_source',
            'pickware_wms_stocking_process',
            'pickware_wms_shipping_method_config',
        ]);
        PickwareWmsInstaller::initFromContainer($this->container)->uninstall($uninstallContext);
        BundleMigrationDropper::createForContainer($this->container)->dropMigrationsForBundle(__NAMESPACE__);
        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
