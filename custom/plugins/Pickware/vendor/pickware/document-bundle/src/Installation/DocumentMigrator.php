<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Installation;

use Exception;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use ReflectionClass;
use RuntimeException;
use Shopware\Core\Content\Media\File\FileSaver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shopware auto creates a filesystem based on the base class name. Because of a naming change in the name of the base
 * class all files need to be moved from the old path to the path of the new base class name. This is a one time
 * migration since after the migration happened for the first time all old files are deleted.
 *
 * @deprecated should not be used, until the performance issues are fixed: https://github.com/pickware/shopware-plugins/issues/4457
 */
class DocumentMigrator
{
    private const OLD_FILESYSTEM_PREFIX = 'plugins/document_bundle/';
    private const NEW_FILESYSTEM_PREFIX = 'plugins/pickware_document_bundle/';

    public function __construct(private readonly FilesystemOperator $shopwarePrivateFileSystem) {}

    public static function createForContainer(ContainerInterface $container): self
    {
        // We use the public service "FileSaver" to get the shopware.filesystem.private service as this service is
        // private in the DI Container.
        $fileSaver = $container->get(FileSaver::class);
        $reflectionClass = new ReflectionClass($fileSaver);
        $reflectionProperty = $reflectionClass->getProperty('filesystemPrivate');
        $reflectionProperty->setAccessible(true);
        /** @var FilesystemOperator $privateFilesystem */
        $privateFilesystem = $reflectionProperty->getValue($fileSaver);

        return new self($privateFilesystem);
    }

    /**
     * @deprecated should not be used, until the performance issues are fixed: https://github.com/pickware/shopware-plugins/issues/4457
     */
    public function moveDirectory(string $dir): void
    {
        if (!$this->shopwarePrivateFileSystem->directoryExists(self::NEW_FILESYSTEM_PREFIX . $dir)) {
            $this->shopwarePrivateFileSystem->createDirectory(self::NEW_FILESYSTEM_PREFIX . $dir);
        }
        if (!$this->shopwarePrivateFileSystem->directoryExists(self::OLD_FILESYSTEM_PREFIX . $dir)) {
            return;
        }

        $dirItems = $this->shopwarePrivateFileSystem->listContents(self::OLD_FILESYSTEM_PREFIX . $dir);
        foreach ($dirItems as $dirItem) {
            if ($dirItem->type() === StorageAttributes::TYPE_FILE) {
                $newFilePath = str_replace(self::OLD_FILESYSTEM_PREFIX, self::NEW_FILESYSTEM_PREFIX, $dirItem->path());
                if ($this->shopwarePrivateFileSystem->fileExists($newFilePath)) {
                    // Files are overwritten if existent
                    $this->shopwarePrivateFileSystem->delete($newFilePath);
                }

                try {
                    $this->shopwarePrivateFileSystem->move($dirItem->path(), $newFilePath);
                } catch (Exception $e) {
                    throw new RuntimeException(
                        message: sprintf(
                            'An error has occurred while moving the file %s from one filesystem to another.',
                            $dirItem->path(),
                        ),
                        previous: $e,
                    );
                }
            }
        }

        $this->shopwarePrivateFileSystem->deleteDirectory(self::OLD_FILESYSTEM_PREFIX . $dir);
    }
}
