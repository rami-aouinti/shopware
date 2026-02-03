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

use Composer\Autoload\ClassLoader;

/**
 * @var int $version This is the version of the dependency loader. Increase this integer when releasing a new version
 *      of the dependency loader.
 */
$version = 2;

// The following code ensures that the latest version of the dependency loader is used.
if (
    isset($GLOBALS['PICKWARE_DEPENDENCY_LOADER_VERSION'])
    && $GLOBALS['PICKWARE_DEPENDENCY_LOADER_VERSION'] >= $version
) {
    return;
}
$GLOBALS['PICKWARE_DEPENDENCY_LOADER_VERSION'] = $version;
$GLOBALS['PICKWARE_DEPENDENCY_LOADER'] = new class () {
    private ClassLoader $classLoader;

    /**
     * @var string[]
     */
    private array $filesToInclude = [];

    private array $packages = [];
    private bool $dependenciesLoaded = false;

    public function __construct()
    {
        $this->classLoader = new ClassLoader();
    }

    /**
     * @param array $plugins An array with assoc arrays with the keys: baseClass, path, managedByComposer
     * @param string $projectDir Dir to Shopware installation (Kernel parameter: kernel.project_dir)
     */
    public function ensureLatestDependenciesOfPluginsLoaded(array $plugins, string $projectDir): void
    {
        if ($this->dependenciesLoaded) {
            return;
        }

        $projectDir = rtrim($projectDir, '/') . '/';

        $this->addPackagesOfPlugins($plugins, $projectDir);

        $this->loadDependencies();
    }

    /**
     * Adds all packages provided by the passed plugins to the dependency loader.
     *
     * @param array $plugins An array with assoc arrays with the keys: baseClass, path, managedByComposer
     */
    private function addPackagesOfPlugins(array $plugins, string $projectDir): void
    {
        foreach ($plugins as $plugin) {
            if ($plugin['managedByComposer']) {
                continue;
            }

            $packages = $this->getDistPackagesOfPlugin($plugin['baseClass']);
            $this->addPackagesAtBasePath($packages, $projectDir . $plugin['path']);
        }
    }

    private function getDistPackagesOfPlugin(string $pluginClass): array
    {
        if (!method_exists($pluginClass, 'getDistPackages')) {
            return [];
        }

        return $pluginClass::getDistPackages();
    }

    /**
     * Adds a package to the known packages
     */
    private function addPackagesAtBasePath(array $packages, string $basePath): void
    {
        $basePath = rtrim($basePath, '/') . '/';
        foreach ($packages as $newPackage) {
            $packageName = $newPackage['name'];
            $newPackage['basePath'] = $basePath;
            if (isset($this->packages[$packageName])) {
                $existingPackage = $this->packages[$packageName];
                if (version_compare($newPackage['version_normalized'], $existingPackage['version_normalized'], '>')) {
                    $this->packages[$packageName] = $newPackage;
                }
            } else {
                $this->packages[$packageName] = $newPackage;
            }
        }
    }

    /**
     * Registers all known packages in the class loader
     */
    private function registerPackageNamespaces(): void
    {
        foreach ($this->packages as $package) {
            $this->registerPackageNamespace(
                $package['basePath'] . 'vendor/' . $package['name'],
                $package['autoload'] ?? [],
            );
        }
    }

    private function registerPackageNamespace(string $packagePath, array $autoload): void
    {
        $psr4 = $autoload['psr-4'] ?? [];
        foreach ($psr4 as $namespace => $paths) {
            if (is_string($paths)) {
                $paths = [$paths];
            }
            $paths = $this->mapRelativePathsToAbsolutePaths($paths, $packagePath);
            $this->classLoader->addPsr4($namespace, $paths);
        }

        $psr0 = $autoload['psr-0'] ?? [];
        foreach ($psr0 as $namespace => $paths) {
            if (is_string($paths)) {
                $paths = [$paths];
            }
            $paths = $this->mapRelativePathsToAbsolutePaths($paths, $packagePath);

            $this->classLoader->add($namespace, $paths);
        }

        $files = $autoload['files'] ?? [];
        foreach ($files as $file) {
            $this->filesToInclude[] = $packagePath . '/' . $file;
        }

        $classMap = $autoload['classmap'] ?? [];
        if (count($classMap) > 0) {
            $classMap = $this->mapRelativePathsToAbsolutePaths($classMap, $packagePath);
            $this->classLoader->addClassMap($classMap);
        }
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    private function mapRelativePathsToAbsolutePaths(array $paths, string $basePath): array
    {
        foreach ($paths as &$path) {
            $path = $basePath . '/' . $path;
        }

        return $paths;
    }

    private function loadDependencies(): void
    {
        $this->registerPackageNamespaces();
        $this->classLoader->register();
        foreach ($this->filesToInclude as $fileToInclude) {
            self::requireFileOnce($fileToInclude);
        }
        $this->dependenciesLoaded = true;
    }

    /**
     * Executes require_once for the given $file.
     *
     * This method is necessary to have a clean scope with no $this or other local variables in the included file.
     */
    private static function requireFileOnce(string $file): void
    {
        require_once $file;
    }
};
