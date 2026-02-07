<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos;

use Pickware\AclBundle\PickwareAclBundle;
use Pickware\ApiErrorHandlingBundle\PickwareApiErrorHandlingBundle;
use Pickware\ApiVersioningBundle\PickwareApiVersioningBundle;
use Pickware\BundleInstaller\BundleInstaller;
use Pickware\DalBundle\PickwareDalBundle;
use Pickware\DocumentBundle\PickwareDocumentBundle;
use Pickware\InstallationLibrary\BundleMigrationDropper;
use Pickware\InstallationLibrary\DependencyAwareTableDropper;
use Pickware\MobileAppAuthBundle\PickwareMobileAppAuthBundle;
use Pickware\PickwarePos\Installation\PickwarePosInstaller;
use Pickware\ShopwareExtensionsBundle\PickwareShopwareExtensionsBundle;
use Pickware\ShopwarePlugins\ShopwareIntegrationTestPlugin\ShopwareIntegrationTestPlugin;
use Pickware\ValidationBundle\PickwareValidationBundle;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class PickwarePosBundle extends Bundle
{
    public const PLUGIN_CONFIG_DOMAIN = 'PickwarePosBundle.sales-channel-plugin-config';
    public const PLUGIN_CONFIG_KEY_PREFIX = self::PLUGIN_CONFIG_DOMAIN . '.';
    public const POS_AUTOMATIC_RECEIPT_PRINTING_CONFIG_KEY = self::PLUGIN_CONFIG_KEY_PREFIX . 'posAutomaticReceiptPrinting';
    public const POS_GROUP_PRODUCT_VARIANTS_CONFIG_KEY = self::PLUGIN_CONFIG_KEY_PREFIX . 'posGroupProductVariants';
    public const POS_OVERSELLING_WARNING_CONFIG_KEY = self::PLUGIN_CONFIG_KEY_PREFIX . 'posOversellingWarning';
    public const POS_RECEIPT_SHOW_LIST_PRICES = self::PLUGIN_CONFIG_KEY_PREFIX . 'posReceiptShowListPrices';
    public const SALES_CHANNEL_TYPE_ID = 'd18beabacf894e14b507767f4358eeb0';

    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [
        PickwareAclBundle::class,
        PickwareApiErrorHandlingBundle::class,
        PickwareApiVersioningBundle::class,
        PickwareDalBundle::class,
        PickwareDocumentBundle::class,
        PickwareMobileAppAuthBundle::class,
        PickwareShopwareExtensionsBundle::class,
        PickwareValidationBundle::class,
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

        // Register test services. Should never be loaded in production.
        if (in_array(ShopwareIntegrationTestPlugin::class, $containerBuilder->getParameter('kernel.bundles'), true)) {
            $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__));
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

        if ($installContext instanceof UpdateContext) {
            PickwarePosInstaller::initFromContainer($this->container)->update($installContext);
        } else {
            PickwarePosInstaller::initFromContainer($this->container)->install($installContext);
        }
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
            'pickware_pos_address',
            'pickware_pos_cash_register',
            'pickware_pos_cash_register_fiskaly_configuration',
            'pickware_pos_cash_point_closing',
            'pickware_pos_cash_point_closing_transaction',
            'pickware_pos_cash_point_closing_transaction_line_item',
            'pickware_pos_branch_store',
            'pickware_pos_order_branch_store_mapping',
            'pickware_pos_order_line_item',
        ]);
        PickwarePosInstaller::initFromContainer($this->container)->uninstall($uninstallContext);
        BundleMigrationDropper::createForContainer($this->container)->dropMigrationsForBundle(__NAMESPACE__);
        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
