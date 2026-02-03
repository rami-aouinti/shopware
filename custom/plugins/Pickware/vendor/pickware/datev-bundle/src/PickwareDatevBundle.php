<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle;

use Pickware\BundleInstaller\BundleInstaller;
use Pickware\DalBundle\PickwareDalBundle;
use Pickware\DatevBundle\Installation\PickwareDatevBundleInstaller;
use Pickware\DocumentBundle\PickwareDocumentBundle;
use Pickware\InstallationLibrary\BundleMigrationDropper;
use Pickware\InstallationLibrary\DependencyAwareTableDropper;
use Pickware\ShopwarePlugins\ShopwareIntegrationTestPlugin\ShopwareIntegrationTestPlugin;
use Pickware\ValidationBundle\PickwareValidationBundle;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class PickwareDatevBundle extends Bundle
{
    // Directly referencing the POS constants via their class constants in `PickwarePos` would lead to a direct
    // dependency on pickware-pos which we want to avoid. All usages of the POS constants should reference these constants.
    public const PICKWARE_POS_SALES_CHANNEL_TYPE_ID = 'd18beabacf894e14b507767f4358eeb0';
    public const PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME = 'pickware_pos_receipt';
    public const PICKWARE_POS_RETURN_ORDER_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME = 'pickware_pos_return_order_receipt';

    // Directly referencing the sales channel type via the class constant in `PickwareShopifyIntegration` would lead to
    // a direct dependency on pickware-shopify-integration which we want to avoid. All usages of the sales channel type
    // should reference this constant.
    public const PICKWARE_SHOPIFY_INTEGRATION_SALES_CHANNEL_TYPE_ID = 'd680e46b708f4f68b601999a311a043d';
    public const PICKWARE_SHOPIFY_UNKNOWN_COUNTRY_ISO_CODE = 'ZZ';

    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [
        PickwareDalBundle::class,
        PickwareValidationBundle::class,
        PickwareDocumentBundle::class,
    ];

    // Note that these are the iso codes of all countries of the european union except germany
    public const ISO_CODES_OF_DESTINATION_COUNTRIES_OF_INTRA_COMMUNITY_DELIVERIES_FROM_GERMANY = [
        'BE', // Belgium
        'BG', // Bulgaria
        'DK', // Denmark
        'EE', // Estonia
        'FI', // Finland
        'FR', // France
        'GR', // Greece
        'IE', // Ireland
        'IT', // Italy
        'HR', // Croatia
        'LV', // Latvia
        'LT', // Lithuania
        'LU', // Luxembourg
        'MT', // Malta
        'NL', // Netherlands
        'AT', // Austria
        'PL', // Poland
        'PT', // Portugal
        'RO', // Romania
        'SE', // Sweden
        'SK', // Slovakia
        'SI', // Slovenia
        'ES', // Spain
        'CZ', // Czech Republic
        'HU', // Hungary
        'CY', // Cyprus
    ];
    public const ISO_CODES_OF_DESTINATION_COUNTRIES_OF_EUROPEAN_UNION_DELIVERIES = [
        ...self::ISO_CODES_OF_DESTINATION_COUNTRIES_OF_INTRA_COMMUNITY_DELIVERIES_FROM_GERMANY,
        'DE', // Germany
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
        $loader->load('EntryBatch/DependencyInjection/import-export.xml');
        $loader->load('PaymentCapture/DependencyInjection/service.xml');
        $loader->load('PosPayment/DependencyInjection/service.xml');
        $loader->load('AccountingDocumentPicture/Export/DependencyInjection/accounting-document-picture-export.xml');

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
        $bundleInstaller = BundleInstaller::createForContainerAndClass($this->container, self::class);
        $bundleInstaller->install(self::ADDITIONAL_BUNDLES, $installContext);

        PickwareDatevBundleInstaller::initFromContainer($this->container)->install($installContext);
    }

    public function onAfterActivate(InstallContext $installContext): void
    {
        $bundleInstaller = BundleInstaller::createForContainerAndClass($this->container, self::class);
        $bundleInstaller->onAfterActivate(self::ADDITIONAL_BUNDLES, $installContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        DependencyAwareTableDropper::createForContainer($this->container)->dropTables([
            'pickware_datev_config',
            'pickware_datev_payment_capture',
            'pickware_datev_accounting_document_guid',
            'pickware_datev_import_export_accounting_document_guid_mapping',
            'pickware_datev_individual_debtor_account_information',
        ]);

        BundleMigrationDropper::createForContainer($this->container)->dropMigrationsForBundle(__NAMESPACE__);

        PickwareDatevBundleInstaller::initFromContainer($this->container)->uninstall();

        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
