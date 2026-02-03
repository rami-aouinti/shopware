<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\Pickware;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Pickware\AustrianPostBundle\PickwareAustrianPostBundle;
use Pickware\BundleInstaller\BundleInstaller;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\DhlExpressBundle\PickwareDhlExpressBundle;
use Pickware\DpdBundle\PickwareDpdBundle;
use Pickware\DsvBundle\PickwareDsvBundle;
use Pickware\InstallationLibrary\PluginInstallationState;
use Pickware\InstallationLibrary\PluginLifecycleErrorRecovery;
use Pickware\LicenseBundle\PickwareLicenseBundle;
use Pickware\PickwareErpPro\PickwareErpPro;
use Pickware\PickwareErpStarter\PickwareErpBundle;
use Pickware\PickwareErpStarter\PickwareErpStarter;
use Pickware\PickwarePos\PickwarePos;
use Pickware\PickwarePos\PickwarePosBundle;
use Pickware\PickwareWms\PickwareWms;
use Pickware\PickwareWms\PickwareWmsBundle;
use Pickware\ProductSetBundle\PickwareProductSetBundle;
use Pickware\SendcloudBundle\PickwareSendcloudBundle;
use Pickware\SwissPostBundle\PickwareSwissPostBundle;
use Pickware\UpsBundle\PickwareUpsBundle;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

if (file_exists(__DIR__ . '/../vendor/pickware/dependency-loader/src/DependencyLoader.php')) {
    require_once __DIR__ . '/../vendor/pickware/dependency-loader/src/DependencyLoader.php';
}

class Pickware extends Plugin
{
    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [
        PickwareAustrianPostBundle::class,
        PickwareDatevBundle::class,
        PickwareDhlExpressBundle::class,
        PickwareDpdBundle::class,
        PickwareDsvBundle::class,
        PickwareErpBundle::class,
        PickwareLicenseBundle::class,
        PickwarePosBundle::class,
        PickwareProductSetBundle::class,
        PickwareSendcloudBundle::class,
        PickwareSwissPostBundle::class,
        PickwareUpsBundle::class,
        PickwareWmsBundle::class,
    ];

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        // Ensure the bundle classes can be loaded via autoloading.
        if (isset($GLOBALS['PICKWARE_DEPENDENCY_LOADER'])) {
            $kernelParameters = $parameters->getKernelParameters();
            // This method is only called with the kernel parameters when invoked by the Kernel.
            // As of Shopware version >= 6.6.1.1, a new behavior was introduced where the Storefront theme is recompiled
            // whenever a new plugin is installed or updated (see Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber).
            // However, during this process, the `getAdditionalBundles` method of each installed bundle is called
            // without the kernel parameters because the call does not pass through the Kernel (see
            // Shopware\StorefrontPluginConfiguration/StorefrontPluginConfigurationFactory.php and
            // Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber.php).
            // Therefore, we can safely ignore this call, as the necessary data is already loaded by the Kernel.
            if (array_key_exists('kernel.plugin_infos', $kernelParameters) && array_key_exists('kernel.project_dir', $kernelParameters)) {
                $GLOBALS['PICKWARE_DEPENDENCY_LOADER']->ensureLatestDependenciesOfPluginsLoaded(
                    $kernelParameters['kernel.plugin_infos'],
                    $kernelParameters['kernel.project_dir'],
                );
            }
        }

        // For some reason Collection is abstract
        /** @var Collection<Bundle> $bundleCollection */
        $bundleCollection = new class () extends Collection {};
        foreach (self::ADDITIONAL_BUNDLES as $bundle) {
            $bundle::register($bundleCollection);
        }

        return $bundleCollection->getElements();
    }

    public static function getDistPackages(): array
    {
        return include __DIR__ . '/../Packages.php';
    }

    public function build(ContainerBuilder $containerBuilder): void
    {
        parent::build($containerBuilder);

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__));
        $loader->load('Installation/DependencyInjection/service.xml');
    }

    public function install(InstallContext $installContext): void
    {
        $this->loadDependenciesForSetup();

        $this->executeMigrationsOfBundles();

        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->install(self::ADDITIONAL_BUNDLES, $installContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->loadDependenciesForSetup();

        $this->executeMigrationsOfBundles();

        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->install(self::ADDITIONAL_BUNDLES, $updateContext);
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
        PluginLifecycleErrorRecovery::createForContainer($this->container)
            ->recoverFromErrorsIn($this->handlePostUpdate(...), $updateContext);
    }

    private function handlePostUpdate(UpdateContext $updateContext): void
    {
        if ($updateContext->getPlugin()->isActive()) {
            $this->copyAssetsFromBundles();

            BundleInstaller::createForContainerAndClass($this->container, self::class)
                ->onAfterActivate(self::ADDITIONAL_BUNDLES, $updateContext);
        }
    }

    private function executeMigrationsOfBundles(): void
    {
        // All the services required for migration execution are private in the DI-Container. As a workaround the
        // services are instantiated explicitly here.
        /** @var Connection $db */
        $db = $this->container->get(Connection::class);
        // See vendor/symfony/monolog-bundle/Resources/config/monolog.xml on how the logger is defined.
        $logger = new Logger('app');
        $logger->useMicrosecondTimestamps($this->container->getParameter('monolog.use_microseconds'));
        $migrationCollectionLoader = new MigrationCollectionLoader(
            connection: $db,
            migrationRuntime: new MigrationRuntime($db, $logger),
            logger: $logger,
        );
        $migrationSource = new MigrationSource('Pickware');

        foreach (self::ADDITIONAL_BUNDLES as $bundle) {
            $bundle::registerMigrations($migrationSource);
        }
        $migrationCollectionLoader->addSource($migrationSource);

        foreach ($migrationCollectionLoader->collectAll() as $migrationCollection) {
            $migrationCollection->sync();
            $migrationCollection->migrateInPlace();
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->onAfterActivate(self::ADDITIONAL_BUNDLES, $activateContext);

        $this->copyAssetsFromBundles();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->loadDependenciesForSetup();

        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        // In case the plugin has replaced the legacy Pickware plugins and those where uninstalled using the
        // "keepUserData" option, these legacy plugin will still mark their bundles as used by them. This will lead to
        // leaving data in the database when removing the Pickware plugin. To remove this data, this plugin will
        // "emulate" the uninstallation of the legacy Pickware plugins, if they are not installed anymore. This should
        // clean up any remaining data from the legacy Pickware plugins when removing the Pickware plugin.
        $legacyPlugins = [
            PickwareErpPro::class,
            PickwareWms::class,
            PickwarePos::class,
            PickwareErpStarter::class,
        ];
        foreach ($legacyPlugins as $legacyPlugin) {
            $installationState = PluginInstallationState::getForPlugin($connection, $legacyPlugin);
            if ($installationState->installed) {
                continue;
            }

            // The legacy plugin is not installed anymore, so we can remove its bundles.
            BundleInstaller::createForContainerAndClass($this->container, $legacyPlugin)->uninstall($uninstallContext);
        }
    }

    /**
     * Run the dependency loader for a setup step like install/update/uninstall
     *
     * When executing one of these steps but no Pickware plugin is activated, the dependency loader did never run until
     * the call of the corresponding method. You can trigger it with a call of this method.
     */
    private function loadDependenciesForSetup(): void
    {
        if (isset($GLOBALS['PICKWARE_DEPENDENCY_LOADER'])) {
            $plugins = $this->container->get('kernel')->getPluginLoader()->getPluginInfos();
            $projectDir = $this->container->getParameter('kernel.project_dir');
            $GLOBALS['PICKWARE_DEPENDENCY_LOADER']->ensureLatestDependenciesOfPluginsLoaded($plugins, $projectDir);
        }
    }

    private function copyAssetsFromBundles(): void
    {
        $this->container
            ->get('pickware.bundle_supporting_asset_service')
            ->copyAssetsFromBundle('PickwareAclBundle')
            ->copyAssetsFromBundle('PickwareAiBundle')
            ->copyAssetsFromBundle('PickwareAustrianPostBundle')
            ->copyAssetsFromBundle('PickwareDatevBundle')
            ->copyAssetsFromBundle('PickwareDhlExpressBundle')
            ->copyAssetsFromBundle('PickwareDpdBundle')
            ->copyAssetsFromBundle('PickwareDsvBundle')
            ->copyAssetsFromBundle('PickwareErpBundle')
            ->copyAssetsFromBundle('PickwareIncompatibilityBundle')
            ->copyAssetsFromBundle('PickwareLicenseBundle')
            ->copyAssetsFromBundle('PickwareMobileAppAuthBundle')
            ->copyAssetsFromBundle('PickwarePosBundle')
            ->copyAssetsFromBundle('PickwareProductSetBundle')
            ->copyAssetsFromBundle('PickwareSendcloudBundle')
            ->copyAssetsFromBundle('PickwareShippingBundle')
            ->copyAssetsFromBundle('PickwareSwissPostBundle')
            ->copyAssetsFromBundle('PickwareUpsBundle')
            ->copyAssetsFromBundle('PickwareUpsellNudgingBundle')
            ->copyAssetsFromBundle('PickwareWmsBundle');
    }
}
