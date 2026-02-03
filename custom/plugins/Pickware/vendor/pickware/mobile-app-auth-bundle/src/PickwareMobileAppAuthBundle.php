<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle;

use Doctrine\DBAL\Connection;
use Pickware\BundleInstaller\BundleInstaller;
use Pickware\DalBundle\PickwareDalBundle;
use Pickware\InstallationLibrary\BundleMigrationDropper;
use Pickware\InstallationLibrary\DependencyAwareTableDropper;
use Pickware\MobileAppAuthBundle\Installation\PickwareMobileAppAuthBundleInstaller;
use Pickware\MobileAppAuthBundle\Installation\Steps\UpsertMobileAppAclRoleInstallationStep;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class PickwareMobileAppAuthBundle extends Bundle
{
    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [PickwareDalBundle::class];

    private static ?PickwareMobileAppAuthBundle $instance = null;
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
        $loader->load('Controller/DependencyInjection/controller.xml');
        $loader->load('DemodataGeneration/DependencyInjection/command.xml');
        $loader->load('DemodataGeneration/DependencyInjection/demodata-generator.xml');
        $loader->load('OAuth/DependencyInjection/model.xml');
        $loader->load('OAuth/DependencyInjection/model-extension.xml');
        $loader->load('OAuth/DependencyInjection/model-subscriber.xml');
        $loader->load('OAuth/DependencyInjection/service.xml');
        $loader->load('OAuth/DependencyInjection/subscriber.xml');
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
    }

    public function onAfterActivate(InstallContext $installContext): void
    {
        $installer = new PickwareMobileAppAuthBundleInstaller($this->container->get(Connection::class));
        $installer->install();

        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->onAfterActivate(self::ADDITIONAL_BUNDLES, $installContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        DependencyAwareTableDropper::createForContainer($this->container)->dropTables(['pickware_mobile_app_credential']);

        $db = $this->container->get(Connection::class);
        // Integrations are created directly by our Pickware apps and should be removed as soon as no authentication
        // is used anymore (this bundle is removed).
        $db->executeStatement('DELETE FROM `integration` WHERE label LIKE \'Pickware App%\'');

        $db->executeStatement('DELETE FROM `acl_role` WHERE id = :aclRoleId', [
            'aclRoleId' => UpsertMobileAppAclRoleInstallationStep::MOBILE_APP_ACL_ROLE_ID_BIN,
        ]);

        BundleMigrationDropper::createForContainer($this->container)->dropMigrationsForBundle(__NAMESPACE__);
        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
