<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle;

use Pickware\BundleInstaller\BundleInstaller;
use Pickware\DalBundle\PickwareDalBundle;
use Pickware\DocumentBundle\Installation\DocumentFileSizeMigrator;
use Pickware\DocumentBundle\Installation\DocumentMigrator;
use Pickware\InstallationLibrary\BundleMigrationDropper;
use Pickware\InstallationLibrary\DependencyAwareTableDropper;
use Pickware\ShopwarePlugins\ShopwareIntegrationTestPlugin\ShopwareIntegrationTestPlugin;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class PickwareDocumentBundle extends Bundle
{
    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [PickwareDalBundle::class];

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
        $migrationSource->addDirectory(__DIR__ . '/MigrationOldNamespace', 'Pickware\\ShopwarePlugins\\DocumentBundle\\Migration');

        self::$migrationsRegistered = true;
    }

    public function install(InstallContext $installContext): void
    {
        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->install(self::ADDITIONAL_BUNDLES, $installContext);
    }

    public function onAfterActivate(InstallContext $activateContext): void
    {
        $documentFileSizeMigrator = $this->container->get(DocumentFileSizeMigrator::class);
        $documentFileSizeMigrator->migrateFileSize();

        DocumentMigrator::createForContainer($this->container)->moveDirectory('documents');

        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->onAfterActivate(self::ADDITIONAL_BUNDLES, $activateContext);
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
        $loader->load('Controller/DependencyInjection/controller.xml');
        $loader->load('Document/DependencyInjection/model.xml');
        $loader->load('Document/DependencyInjection/model-subscriber.xml');
        $loader->load('Document/DependencyInjection/service.xml');
        $loader->load('Document/DependencyInjection/subscriber.xml');
        $loader->load('Installation/DependencyInjection/service.xml');
        $loader->load('Renderer/DependencyInjection/service.xml');

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

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        DependencyAwareTableDropper::createForContainer($this->container)->dropTables([
            'pickware_document',
            'pickware_document_type',
        ]);

        BundleMigrationDropper::createForContainer($this->container)
            ->dropMigrationsForBundle(__NAMESPACE__)
            ->dropMigrationsForBundle('Pickware\\ShopwarePlugins\\DocumentBundle');

        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
