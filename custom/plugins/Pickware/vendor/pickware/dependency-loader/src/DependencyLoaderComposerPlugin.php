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

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

// phpcs:disable ShopwarePlugins.Functions.NativeJsonMethods

class DependencyLoaderComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'generatePackagesPhp',
            ScriptEvents::POST_UPDATE_CMD => 'generatePackagesPhp',
        ];
    }

    public function generatePackagesPhp(Event $composerEvent): void
    {
        $rootPackage = $composerEvent->getComposer()->getPackage();
        if ($this->isRequiredByPackage($rootPackage)) {
            // Generate the Packages.php only if this composer plugin is required by the root package (direct
            // dependency)
            return;
        }

        (new PackagesPhpFile(getcwd()))->save();
    }

    private function isRequiredByPackage(PackageInterface $rootPackage): bool
    {
        $rootPackageRequires = $rootPackage->getRequires();
        $rootPackageRequiresPackageNames = array_map(fn(Link $package) => $package->getTarget(), $rootPackageRequires);

        return !in_array($this->getComposerPackageName(), $rootPackageRequiresPackageNames, true);
    }

    private function getComposerPackageName(): string
    {
        $composerJson = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

        return $composerJson['name'];
    }
}
