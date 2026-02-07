<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Shopware\Core\Framework\Plugin\Util\AssetService;

/**
 * Wraps the original asset service but adds a workaround for this bug: https://github.com/shopware/shopware/pull/1607
 *
 * Shopware's AssetService copies the Javascript of bundles to a directory that has the postfix "bundle" removed.
 * E.g. the Javascript for the ShippingBundle is copied to public/bundles/pickwareshipping instead of
 * public/bundles/pickwareshippingbundle. This Service wraps the original service but also create a copy of the asset
 * directory with the correct name. So in this example public/bundles/pickwareshipping is copied to
 * public/bundles/pickwareshippingbundle
 */
class BundleSupportingAssetService
{
    public function __construct(
        private readonly AssetService $assetService,
        private readonly FilesystemOperator $assetFileSystem,
    ) {}

    public function copyAssetsFromBundle(string $bundleName): self
    {
        $this->assetService->copyAssetsFromBundle($bundleName);

        $lowerCaseBundleName = mb_strtolower($bundleName);
        if (str_ends_with($lowerCaseBundleName, 'bundle')) {
            $sourcePath = 'bundles/' . mb_substr($lowerCaseBundleName, 0, -6);
            $destinationPath = 'bundles/' . $lowerCaseBundleName;
            $this->copyDirectoryRecursive($sourcePath, $destinationPath);
        }

        return $this;
    }

    private function copyDirectoryRecursive(string $originDir, string $targetDir): void
    {
        $this->assetFileSystem->createDirectory($targetDir);

        $contents = $this->assetFileSystem->listContents($originDir);
        foreach ($contents as $fileNode) {
            if ($fileNode->type() === StorageAttributes::TYPE_DIRECTORY) {
                $this->copyDirectoryRecursive($originDir . '/' . basename($fileNode->path()), $targetDir . '/' . basename($fileNode->path()));
            } else {
                $this->assetFileSystem->copy($fileNode->path(), $targetDir . '/' . basename($fileNode->path()));
            }
        }
    }
}
