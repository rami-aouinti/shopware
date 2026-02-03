<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle;

use Pickware\BundleInstaller\BundleInstaller;
use Pickware\DalBundle\PickwareDalBundle;
use Pickware\DocumentBundle\PickwareDocumentBundle;
use Pickware\DpdBundle\Installation\PickwareDpdBundleInstaller;
use Pickware\FeatureFlagBundle\PickwareFeatureFlagBundle;
use Pickware\ShippingBundle\PickwareShippingBundle;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PickwareDpdBundle extends Bundle
{
    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [
        PickwareDalBundle::class,
        PickwareShippingBundle::class,
        PickwareDocumentBundle::class,
        PickwareFeatureFlagBundle::class,
    ];

    private static ?self $instance = null;
    private static bool $registered = false;
    private static bool $migrationsRegistered = false;

    /**
     * @param Collection<Bundle> $bundleCollection
     */
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
        $loader->load('Api/DependencyInjection/service.xml');
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
        PickwareDpdBundleInstaller::initFromContainer($this->container)->install($installContext->getContext());
    }

    public function onAfterActivate(InstallContext $installContext): void
    {
        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->onAfterActivate(self::ADDITIONAL_BUNDLES, $installContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        PickwareDpdBundleInstaller::initFromContainer($this->container)->uninstall($uninstallContext->getContext());
        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
