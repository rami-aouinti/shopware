<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DependencyLoader;

use Composer\Autoload\ClassMapGenerator;
use Symfony\Component\Yaml\Parser;

// phpcs:disable ShopwarePlugins.Functions.NativeJsonMethods

/**
 * @phpstan-type ComposerPackageAutoload array{
 *     classmap?: list<string>,
 *     exclude-from-classmap?: list<string>,
 * }
 * @phpstan-type ComposerExtra array{package-exclusively-with-plugins?: list<string>}
 * @phpstan-type ComposerPackage array{
 *      name: string,
 *      version_normalized: string,
 *      autoload?: ComposerPackageAutoload,
 *      extra?: ComposerExtra,
 *      type: string
 *  }
 * @phpstan-type InstalledComposerPackage ComposerPackage&array{
 *     version_normalized: string,
 * }
 * @phpstan-type FilteredComposerPackage array{
 *     name: string,
 *     version_normalized: string,
 *     autoload: ComposerPackageAutoload,
 *     type: string,
 *     extra: ComposerExtra
 * }
 * @phpstan-type ExcludeList list<string>
 */
class PackagesPhpFile
{
    private string $pluginDir;

    public function __construct(string $pluginDir)
    {
        $this->pluginDir = rtrim($pluginDir, '/') . '/';
    }

    public function save(): void
    {
        $installedPackages = $this->readInstalledPackages();
        /** @var ComposerPackage $plugin */
        $plugin = json_decode(
            file_get_contents($this->pluginDir . 'composer.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
        $packagesToShipWithPlugin = $this->filterPackages($installedPackages, $plugin);
        $packagesToShipWithPlugin = $this->buildClassMaps($packagesToShipWithPlugin);
        $this->generatePackagesPhpForPackages($packagesToShipWithPlugin);
    }

    /**
     * @return list<FilteredComposerPackage>
     */
    private function readInstalledPackages(): array
    {
        echo "################################\n";
        echo "## Reading installed packages ##\n";
        echo "################################\n\n";

        $installedJsonPath = $this->pluginDir . 'vendor/composer/installed.json';

        /** @var array{packages: list<InstalledComposerPackage>, dev-package-names?: list<string>} $installed */
        $installed = json_decode(file_get_contents($installedJsonPath), true, 512, JSON_THROW_ON_ERROR);
        $installedPackages = array_map(
            fn(array $package) => [
                'name' => $package['name'],
                'version_normalized' => $package['version_normalized'],
                'autoload' => $package['autoload'] ?? [],
                'type' => $package['type'],
                'extra' => $package['extra'] ?? [],
            ],
            $installed['packages'],
        );
        $devPackageNames = $installed['dev-package-names'] ?? [];
        // Dev dependencies should not be loaded, as they are only required for unit tests. The dev dependencies of
        // plugins should not pollute the actual Shopware installation with unnecessary packages.
        $installedPackages = array_values(array_filter(
            $installedPackages,
            fn(array $package) => !in_array($package['name'], $devPackageNames, true),
        ));

        printf("%d installed packages found\n\n", count($installedPackages));

        return $installedPackages;
    }

    /**
     * @param list<FilteredComposerPackage> $installedPackages
     * @param ComposerPackage $plugin
     * @return list<FilteredComposerPackage>
     */
    private function filterPackages(array $installedPackages, array $plugin): array
    {
        echo "#########################################################\n";
        echo "## Filtering dependencies that are shipped by Shopware ##\n";
        echo "#########################################################\n\n";

        /** @var ExcludeList $dependencyExcludeList */
        $dependencyExcludeList = (new Parser())->parseFile(__DIR__ . '/../Resources/dependency-exclude-list.yaml');
        $packagesToShipWithPlugin = array_values(array_filter(
            $installedPackages,
            function($package) use ($plugin, $dependencyExcludeList) {
                if (mb_strtolower($package['type']) === 'composer-plugin') {
                    // Composer plugins have to be shipped always otherwise when Shopware tries to load the plugin
                    // package composer will fail with a cryptic error message because it cannot find the plugin.
                    return true;
                }

                // Plugins should never be shipped with other plugins
                if (mb_strtolower($package['type']) === 'shopware-platform-plugin') {
                    return false;
                }

                if (
                    isset($package['extra']['package-exclusively-with-plugins'])
                    && !in_array($plugin['name'], $package['extra']['package-exclusively-with-plugins'], true)
                ) {
                    return false;
                }

                return !in_array($package['name'], $dependencyExcludeList, true);
            },
        ));

        echo "The following dependencies will be shipped with this plugin:\n\n";

        foreach ($packagesToShipWithPlugin as $package) {
            printf("    %s: %s\n", $package['name'], $package['version_normalized']);
        }

        printf("\nTotal: %s\n\n", count($packagesToShipWithPlugin));

        return $packagesToShipWithPlugin;
    }

    /**
     * @param list<FilteredComposerPackage> $packagesToShipWithPlugin
     */
    private function generatePackagesPhpForPackages(array $packagesToShipWithPlugin): void
    {
        echo "#############################\n";
        echo "## Generating Packages.php ##\n";
        echo "#############################\n\n";

        $packagesPhpFileName = $this->pluginDir . 'Packages.php';

        printf("Destination path: %s\n\n", $packagesPhpFileName);

        $packagesPhpTemplate = <<<PACKAGES_PHP_TEMPLATE
            <?php
            // THIS FILE IS AUTO-GENERATED.
            // Do not modify it, your changes will be lost after the next "composer install/updates" execution.
            // This file contains a list of composer packages that are shipped with this plugin and their autoloader information.
            // This file is explicitly a PHP file (and not JSON or YAML) to make it cacheable for the OpCache.
            return %s;
            PACKAGES_PHP_TEMPLATE;
        $packagesPhpContents = sprintf($packagesPhpTemplate, var_export($packagesToShipWithPlugin, true));
        file_put_contents($packagesPhpFileName, $packagesPhpContents);

        echo "Packages.php has been generated!\n\n";
    }

    /**
     * @param list<FilteredComposerPackage> $packages
     * @return list<FilteredComposerPackage>
     */
    private function buildClassMaps(array $packages): array
    {
        foreach ($packages as &$package) {
            $classMap = $package['autoload']['classmap'] ?? [];
            $excludeFromClassMap = $package['autoload']['exclude-from-classmap'] ?? [];
            if (count($excludeFromClassMap) !== 0) {
                $blacklistRegex = self::convertBlacklistToRegex($excludeFromClassMap);
            } else {
                $blacklistRegex = null;
            }
            $classMaps = [];
            foreach ($classMap as $classMapPath) {
                $dependencyPath = $this->pluginDir . 'vendor/' . $package['name'] . '/';
                $classMap = ClassMapGenerator::createMap($dependencyPath . $classMapPath, $blacklistRegex);
                $classMapWithRelativePaths = $this->convertAbsolutePathsToRelativePaths($classMap, $dependencyPath);
                $classMaps[] = $classMapWithRelativePaths;
            }

            $package['autoload']['classmap'] = array_merge([], ...$classMaps);
            unset($package['autoload']['exclude-from-classmap']);
        }

        return $packages;
    }

    /**
     * Converts an array of matching-pattern to a single regex string.
     *
     * @param string[] $blacklist
     */
    private static function convertBlacklistToRegex(array $blacklist): string
    {
        return '{(' . implode('|', $blacklist) . ')}';
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    private function convertAbsolutePathsToRelativePaths(array $paths, string $basePath): array
    {
        foreach ($paths as &$path) {
            if (mb_strpos($path, $basePath) === 0) {
                $path = mb_substr($path, mb_strlen($basePath));
            }
        }

        return $paths;
    }
}
